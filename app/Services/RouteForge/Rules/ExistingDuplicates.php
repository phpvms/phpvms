<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Flight;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Support\StrictDuplicateKey;

/**
 * L5 + L12 — Existing-flight duplicate detection.
 *
 * One rule, two emitted issue shapes:
 *
 *   - **L5 (ERROR)** — Same-bundle full-tuple collision. A submitted batch row
 *     matches an existing enabled non-owner flight on the 5-tuple
 *     `(bundle_id, airline_id, flight_number, route_code, route_leg)`. The DB
 *     UNIQUE index on `flights._dup_key` would reject the commit; surfacing as
 *     ERROR blocks before the constraint does.
 *
 *   - **L12 (WARNING)** — Cross-bundle airline+flight collision. The submitted
 *     row shares `(airline_id, flight_number)` with an existing enabled
 *     non-owner flight in a **different** bundle than the batch. Operational
 *     concern: the flight number is in use elsewhere for this airline.
 *
 * Merged into one rule (post-`route-forge` lint-cleanup) to share a single
 * bulk query against the airline × flight_number candidate space. The old
 * split (`L5ExistingDuplicate` + `L12ExistingDuplicateCrossBundle`) ran two
 * near-identical queries per lint pass.
 *
 * Owner-typed flights (charter/personal) have a separate flight-number
 * namespace and are excluded from both checks.
 *
 * New-bundle path: when `$ctx->bundle->id` is null (batch creates a new
 * bundle), no existing flight can be in the not-yet-persisted bundle, so the
 * L5 branch short-circuits per-row and every airline+flight match becomes
 * an L12 warning.
 *
 * Wire shape is unchanged: emitted issues carry `ruleId='L5'` or
 * `ruleId='L12'` exactly as before, with the same `details` payloads, so the
 * client RowLintIcon + LintReportDialog keep grouping by rule id without
 * modification.
 */
final class ExistingDuplicates implements LintRule
{
    public const string SAME_BUNDLE_RULE_ID = 'L5';

    public const string CROSS_BUNDLE_RULE_ID = 'L12';

    public const LintSeverity SAME_BUNDLE_SEVERITY = LintSeverity::Error;

    public const LintSeverity CROSS_BUNDLE_SEVERITY = LintSeverity::Warning;

    public function check(LintContext $ctx): array
    {
        if ($ctx->rows === []) {
            return [];
        }

        $airlineIds = $ctx->uniqueAirlineIds();
        $flightNumbers = $ctx->uniqueFlightNumbers();

        if ($airlineIds === [] || $flightNumbers === []) {
            return [];
        }

        // `exists` distinguishes a persisted batch bundle (id is a real int)
        // from an unsaved one (id is null even though Eloquent's typed
        // accessor declares `int`). Null = new-bundle path, all matches L12.
        $batchBundleId = $ctx->bundle->exists ? (int) $ctx->bundle->id : null;

        // ONE bulk query covers both rules. `bundle:id,name` eager-loaded for
        // the L12 message body. No N+1 even at the 100-row batch cap.
        $existing = Flight::query()
            ->where('enabled', true)
            ->whereNull('owner_type')
            ->whereIn('airline_id', $airlineIds)
            ->whereIn('flight_number', $flightNumbers)
            ->with('bundle:id,name')
            ->get(['id', 'bundle_id', 'airline_id', 'flight_number', 'route_code', 'route_leg']);

        if ($existing->isEmpty()) {
            return [];
        }

        // Two indexes off the same row set:
        //   - full 5-tuple → Flight: O(1) same-bundle L5 lookup
        //   - (airline, flight) → list<Flight>: cross-bundle L12 lookup
        /** @var array<string, Flight> $byStrictKey */
        $byStrictKey = StrictDuplicateKey::index(
            $existing,
            static fn (Flight $flight): StrictDuplicateKey => StrictDuplicateKey::forFlight($flight),
        );

        /** @var array<string, list<Flight>> $byCrossKey */
        $byCrossKey = [];
        foreach ($existing as $flight) {
            $key = StrictDuplicateKey::crossBundleKey(
                (int) $flight->airline_id,
                (int) $flight->flight_number,
            );
            $byCrossKey[$key][] = $flight;
        }

        $issues = [];

        foreach ($ctx->rows as $row) {
            if ($row->airlineId === null) {
                continue;
            }

            if ($row->flightNumber === null) {
                continue;
            }

            // L5 — same-bundle full 5-tuple match (ERROR). Only fires when
            // the batch bundle is already persisted; an unsaved bundle has
            // no existing flights by definition.
            if ($batchBundleId !== null) {
                $strictKey = (string) StrictDuplicateKey::forRow($row->raw, $batchBundleId);
                $sameBundleHit = $byStrictKey[$strictKey] ?? null;
                if ($sameBundleHit !== null && (int) $sameBundleHit->bundle_id === $batchBundleId) {
                    $issues[] = new LintIssue(
                        ruleId: self::SAME_BUNDLE_RULE_ID,
                        severity: self::SAME_BUNDLE_SEVERITY,
                        message: __('filament.routeforge.lint.l5_existing_duplicate', [
                            'flight_number' => $row->flightNumber,
                        ]),
                        rowIndex: $row->index,
                        details: [
                            'existing_flight_id' => $sameBundleHit->id,
                            'flight_number'      => $row->flightNumber,
                            'airline_id'         => $row->airlineId,
                        ],
                    );
                }
            }

            // L12 — cross-bundle airline+flight match (WARNING). Fires for
            // every match in a different bundle. New-bundle path keeps all
            // matches; persisted-bundle path filters out same-bundle hits
            // (those are L5's territory above).
            $crossKey = StrictDuplicateKey::crossBundleKey($row->airlineId, $row->flightNumber);
            $crossHits = $byCrossKey[$crossKey] ?? [];

            foreach ($crossHits as $hit) {
                if ($batchBundleId !== null && (int) $hit->bundle_id === $batchBundleId) {
                    continue;
                }

                // bundle_id is NOT NULL (FK) and the relation is eager-loaded
                // above, so the bundle is guaranteed non-null here.
                $bundleName = $hit->bundle->name;

                $issues[] = new LintIssue(
                    ruleId: self::CROSS_BUNDLE_RULE_ID,
                    severity: self::CROSS_BUNDLE_SEVERITY,
                    message: __('filament.routeforge.lint.l12_existing_duplicate_cross_bundle', [
                        'flight_number' => $row->flightNumber,
                        'bundle_name'   => $bundleName,
                    ]),
                    rowIndex: $row->index,
                    details: [
                        'existing_flight_id'   => $hit->id,
                        'flight_number'        => $row->flightNumber,
                        'airline_id'           => $row->airlineId,
                        'existing_bundle_id'   => $hit->bundle_id,
                        'existing_bundle_name' => $bundleName,
                    ],
                );
            }
        }

        return $issues;
    }
}
