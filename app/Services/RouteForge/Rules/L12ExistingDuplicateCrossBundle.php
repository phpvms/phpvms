<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Flight;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\LintRow;
use App\Services\RouteForge\Support\StrictDuplicateKey;

/**
 * L12 — Cross-bundle airline+flight_number collision (WARNING).
 *
 * Fires when a submitted batch row shares `(airline_id, flight_number)` with
 * an existing enabled, non-owner flight in a **different** bundle than the
 * batch's bundle. The full 5-tuple match (same bundle, full key) is L5's
 * domain at ERROR severity; this rule surfaces the softer "this flight
 * number is in use elsewhere" case so admins can review before commit.
 *
 * Partial key: `(airline_id, flight_number)` only. `route_code` and
 * `route_leg` don't participate — the operational concern is "is this flight
 * number already used anywhere for this airline?", not full tuple collision.
 *
 * Disabled flights and owner-typed flights are excluded — they don't occupy
 * the operational flight-number namespace.
 *
 * New-bundle path: when `$ctx->bundle->id === null` (creating a new bundle),
 * "different bundle" is "any existing bundle", so the bundle-exclusion clause
 * is dropped — every existing match fires L12.
 *
 * Single bulk query: pulls every enabled, non-owner flight matching the
 * batch's airline × flight_number space (excluding same-bundle rows when the
 * batch bundle is persisted), eager-loads `bundle:id,name` for the warning
 * message, and indexes by `StrictDuplicateKey::crossBundleKey` for O(1) match
 * lookup per row. No N+1 even at the 100-row batch cap.
 */
final class L12ExistingDuplicateCrossBundle implements LintRule
{
    public const string ID = 'L12';

    public const LintSeverity SEVERITY = LintSeverity::Warning;

    public function check(LintContext $ctx): array
    {
        if ($ctx->rows === []) {
            return [];
        }

        $airlineIds = $this->uniqueAirlineIds($ctx->rows);
        $flightNumbers = $this->uniqueFlightNumbers($ctx->rows);

        if ($airlineIds === [] || $flightNumbers === []) {
            return [];
        }

        // `exists` distinguishes a persisted batch bundle (id is a real int)
        // from an unsaved one (id is null even though Eloquent's typed
        // accessor declares `int`). On the new-bundle path every match is
        // cross-bundle by definition.
        $batchBundleId = $ctx->bundle->exists ? (int) $ctx->bundle->id : null;

        $query = Flight::query()
            ->where('enabled', true)
            ->whereNull('owner_type')
            ->whereIn('airline_id', $airlineIds)
            ->whereIn('flight_number', $flightNumbers)
            ->with('bundle:id,name');

        // When the batch bundle is persisted, exclude same-bundle rows (those
        // are L5's territory). New-bundle path keeps every match since no
        // existing flight could be in the not-yet-persisted bundle anyway.
        if ($batchBundleId !== null) {
            $query->where('bundle_id', '!=', $batchBundleId);
        }

        $existing = $query->get(['id', 'bundle_id', 'airline_id', 'flight_number']);

        if ($existing->isEmpty()) {
            return [];
        }

        // Group existing matches by crossBundleKey. A given (airline, flight)
        // pair may match flights in multiple bundles; we emit one issue per
        // match so the admin sees all conflicts.
        /** @var array<string, list<Flight>> $byKey */
        $byKey = [];
        foreach ($existing as $flight) {
            $key = StrictDuplicateKey::crossBundleKey(
                (int) $flight->airline_id,
                (int) $flight->flight_number,
            );
            $byKey[$key][] = $flight;
        }

        $issues = [];
        foreach ($ctx->rows as $row) {
            if ($row->airlineId === null) {
                continue;
            }
            if ($row->flightNumber === null) {
                continue;
            }
            $key = StrictDuplicateKey::crossBundleKey($row->airlineId, $row->flightNumber);
            $hits = $byKey[$key] ?? null;
            if ($hits === null) {
                continue;
            }

            foreach ($hits as $hit) {
                // bundle_id is NOT NULL (FK) and the relation is eager-loaded
                // above, so bundle is guaranteed non-null here.
                $bundleName = $hit->bundle->name;

                $issues[] = new LintIssue(
                    ruleId: self::ID,
                    severity: self::SEVERITY,
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

    /**
     * @param  list<LintRow> $rows
     * @return list<int>
     */
    private function uniqueAirlineIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if ($row->airlineId !== null) {
                $ids[$row->airlineId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  list<LintRow> $rows
     * @return list<int>
     */
    private function uniqueFlightNumbers(array $rows): array
    {
        $nums = [];
        foreach ($rows as $row) {
            if ($row->flightNumber !== null) {
                $nums[$row->flightNumber] = true;
            }
        }

        return array_keys($nums);
    }
}
