<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Models\Flight;
use App\Services\RouteForge\Support\StrictDuplicateKey;

/**
 * Detects collisions between submitted RouteForge rows and existing flights.
 *
 * Mirrors the bulk-query shape of the L5 / L12 lint rules but lives as a
 * standalone service so the `/admin/route-forge/api/check-duplicates` endpoint
 * can answer the interactive UI "is this flight number taken?" question
 * without spinning up a full LintContext.
 *
 * Classification (post-refinement):
 *   - **same_bundle, error**: existing enabled, non-owner flight in the same
 *     bundle as the batch matches the submitted row on the full 5-tuple
 *     `(bundle_id, airline_id, flight_number, route_code, route_leg)`.
 *     Mirrors L5 ERROR semantics — DB UNIQUE index would reject the commit.
 *   - **cross_bundle, warning**: existing enabled, non-owner flight in a
 *     DIFFERENT bundle shares `(airline_id, flight_number)` with the
 *     submitted row. Mirrors L12 WARNING — surfaces the soft conflict so the
 *     admin can review before commit.
 *   - **no match**: row is absent from the response.
 *
 * Disabled flights and owner-typed flights (charter/personal) are excluded;
 * they don't occupy the org-level flight-number namespace.
 *
 * New-bundle path: when `$batchBundleId` is null (creating a new bundle), no
 * same-bundle match is possible by definition. Every cross-bundle airline+
 * flight match becomes a `cross_bundle` warning.
 *
 * NOT used during commit. Commit-time integrity flows through the
 * `LintRunner` (L4 + L5 + L12) and the DB-level UNIQUE constraint on
 * `flights._dup_key`. This service exists purely to power the typing-time
 * UI check.
 */
final class DuplicateChecker
{
    /**
     * Find existing-flight collisions for each submitted row.
     *
     * One round-trip: pull every enabled, non-owner flight in the candidate
     * `(airline_id, flight_number)` space — bounded by the batch row cap —
     * then classify each submitted row in memory by comparing the full
     * 5-tuple (for same-bundle hits) and the airline+flight pair (for
     * cross-bundle hits). The airline relation is eager-loaded so the
     * `Flight::ident` accessor stays free of N+1. The bundle relation is
     * eager-loaded for the `existing_bundle_name` field in the response.
     *
     * The returned array is keyed by submitted row index. When a row matches
     * multiple existing flights (e.g. one in the same bundle + one in a
     * different bundle), the same-bundle ERROR entry takes precedence; the
     * caller already has L5 / L12 to surface the full set if needed.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{
     *     index: int,
     *     existing_flight_id: string,
     *     ident: string,
     *     conflict_field: 'flight_number',
     *     severity: 'error'|'warning',
     *     kind: 'same_bundle'|'cross_bundle',
     *     existing_bundle_id: int,
     *     existing_bundle_name: string,
     * }>
     */
    public function check(array $rows, ?int $batchBundleId): array
    {
        if ($rows === []) {
            return [];
        }

        $airlineIds = $this->uniqueAirlineIds($rows);
        $flightNumbers = $this->uniqueFlightNumbers($rows);

        if ($airlineIds === [] || $flightNumbers === []) {
            return [];
        }

        $existing = Flight::query()
            // Flight::ident reads `airline->code`, which is an accessor over
            // `iata ?? icao`. Eager-load both backing columns; selecting the
            // accessor name directly returns the airline with NEITHER backing
            // column populated, and ident drops the airline prefix.
            ->with(['airline:id,iata,icao', 'bundle:id,name'])
            ->where('enabled', true)
            ->whereNull('owner_type')
            ->whereIn('airline_id', $airlineIds)
            ->whereIn('flight_number', $flightNumbers)
            ->get(['id', 'bundle_id', 'airline_id', 'flight_number', 'route_code', 'route_leg']);

        if ($existing->isEmpty()) {
            return [];
        }

        // Two indexes:
        //   - Full 5-tuple → Flight: O(1) same-bundle ERROR lookup
        //   - (airline, flight) → list<Flight>: cross-bundle WARNING lookup
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

        $duplicates = [];
        foreach ($rows as $index => $row) {
            $airlineId = $row['airline_id'] ?? null;
            $flightNumber = $row['flight_number'] ?? null;

            // Same-bundle full-key match takes precedence (ERROR).
            if ($batchBundleId !== null) {
                $strictKey = (string) StrictDuplicateKey::forRow($row, $batchBundleId);
                $hit = $byStrictKey[$strictKey] ?? null;
                if ($hit !== null && (int) $hit->bundle_id === $batchBundleId) {
                    $duplicates[$index] = $this->entry($index, $hit, 'error', 'same_bundle');

                    continue;
                }
            }
            // Cross-bundle airline+flight match (WARNING). When batch bundle
            // is null (new-bundle path), every hit is cross-bundle. When the
            // batch bundle is set, exclude same-bundle hits (they belong to
            // L5 / the same-bundle path above and would only collide if the
            // full 5-tuple didn't match — in that case they don't surface).
            if (!is_numeric($airlineId)) {
                continue;
            }
            if (!is_numeric($flightNumber)) {
                continue;
            }

            $crossKey = StrictDuplicateKey::crossBundleKey((int) $airlineId, (int) $flightNumber);
            $crossHits = $byCrossKey[$crossKey] ?? [];

            foreach ($crossHits as $hit) {
                if ($batchBundleId !== null && (int) $hit->bundle_id === $batchBundleId) {
                    continue;
                }

                $duplicates[$index] = $this->entry($index, $hit, 'warning', 'cross_bundle');
                break;
            }
        }

        return $duplicates;
    }

    /**
     * @return array{
     *     index: int,
     *     existing_flight_id: string,
     *     ident: string,
     *     conflict_field: 'flight_number',
     *     severity: 'error'|'warning',
     *     kind: 'same_bundle'|'cross_bundle',
     *     existing_bundle_id: int,
     *     existing_bundle_name: string,
     * }
     */
    private function entry(int $index, Flight $hit, string $severity, string $kind): array
    {
        return [
            'index'              => $index,
            'existing_flight_id' => (string) $hit->id,
            'ident'              => (string) $hit->ident,
            'conflict_field'     => 'flight_number',
            'severity'           => $severity,
            'kind'               => $kind,
            'existing_bundle_id' => (int) $hit->bundle_id,
            // bundle_id is NOT NULL (FK) and `bundle` is eager-loaded by
            // the query above, so the relation is guaranteed non-null here.
            'existing_bundle_name' => (string) $hit->bundle->name,
        ];
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
