<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Flight;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Support\StrictDuplicateKey;

/**
 * L5 — Same-bundle collision with existing non-owner flight in the DB (ERROR).
 *
 * Fires when a submitted batch row matches an existing flight on the full
 * 5-tuple `(bundle_id, airline_id, flight_number, route_code, route_leg)`
 * AND the existing flight has `enabled = true` AND `owner_type IS NULL` AND
 * its `bundle_id` equals the batch's bundle.
 *
 * Same-bundle full-key match is unambiguously a duplicate at the org-level
 * flight-number namespace: the DB UNIQUE index on `flights._dup_key` will
 * reject it on commit. Surfacing as ERROR matches the constraint semantics
 * and blocks the commit before the DB does.
 *
 * Cross-bundle conflicts (same airline + flight_number across bundles) are
 * handled by L12 (`L12ExistingDuplicateCrossBundle`) at WARNING severity.
 *
 * Owner-typed flights (charter/personal) have a separate flight-number
 * namespace and are excluded from this check, matching the spec.
 *
 * When the lint context's bundle is unsaved (`id === null`) — the case where
 * the admin is creating a new bundle — this rule short-circuits: no existing
 * flights can collide with a not-yet-persisted bundle.
 *
 * Bulk-queries every flight in the airline × flight_number space matching the
 * scoping filters. The query MUST be a single round-trip — N+1 would be
 * untenable at the 100-row cap.
 */
final class L5ExistingDuplicate implements LintRule
{
    public function id(): string
    {
        return 'L5';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_ERROR;
    }

    public function check(LintContext $ctx): array
    {
        if ($ctx->rows === []) {
            return [];
        }

        // New-bundle path: bundle isn't persisted yet, no existing flight can
        // belong to it, so no same-bundle dup can exist. L12 still applies
        // for cross-bundle airline+flight conflicts. `exists` distinguishes a
        // persisted bundle (id is a real int) from an unsaved one (id is null
        // even though Eloquent's typed accessor declares `int`).
        if (!$ctx->bundle->exists) {
            return [];
        }

        $bundleId = (int) $ctx->bundle->id;

        $airlineIds = $this->uniqueAirlineIds($ctx->rows);
        $flightNumbers = $this->uniqueFlightNumbers($ctx->rows);

        if ($airlineIds === [] || $flightNumbers === []) {
            return [];
        }

        // One bulk query: pull every enabled, non-owner flight in this bundle
        // matching airline × flight_number, then narrow in-memory by full
        // 5-tuple key match.
        $existing = Flight::query()
            ->where('bundle_id', $bundleId)
            ->where('enabled', true)
            ->whereNull('owner_type')
            ->whereIn('airline_id', $airlineIds)
            ->whereIn('flight_number', $flightNumbers)
            ->get(['id', 'bundle_id', 'airline_id', 'flight_number', 'route_code', 'route_leg']);

        /** @var array<string, Flight> $byKey */
        $byKey = StrictDuplicateKey::index(
            $existing,
            static fn (Flight $flight): StrictDuplicateKey => StrictDuplicateKey::forFlight($flight),
        );

        $issues = [];
        foreach ($ctx->rows as $index => $row) {
            $key = (string) StrictDuplicateKey::forRow($row, $bundleId);

            $hit = $byKey[$key] ?? null;
            if ($hit === null) {
                continue;
            }

            $issues[] = new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l5_existing_duplicate', [
                    'flight_number' => $row['flight_number'] ?? '',
                ]),
                rowIndex: $index,
                details: [
                    'existing_flight_id' => $hit->id,
                    'flight_number'      => $row['flight_number'] ?? null,
                    'airline_id'         => $row['airline_id'] ?? null,
                ],
            );
        }

        return $issues;
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return list<int>
     */
    private function uniqueAirlineIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row['airline_id'] ?? null;
            if (is_numeric($id)) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return list<int>
     */
    private function uniqueFlightNumbers(array $rows): array
    {
        $nums = [];
        foreach ($rows as $row) {
            $num = $row['flight_number'] ?? null;
            if (is_numeric($num)) {
                $nums[(int) $num] = true;
            }
        }

        return array_keys($nums);
    }
}
