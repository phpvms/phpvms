<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Models\Flight;
use Illuminate\Support\Collection;

/**
 * Detects collisions between submitted RouteForge rows and existing flights.
 *
 * Mirrors the bulk-query shape of the L5 lint rule but lives as a standalone
 * service so the /admin/route-forge/api/check-duplicates endpoint can answer
 * the interactive UI "is this flight number taken?" question without spinning
 * up a full LintContext. Both use the strict 4-tuple key
 * `(airline_id, flight_number, route_code, route_leg)` scoped to
 * `owner_type IS NULL`; that namespace is what the spec defines as a
 * "duplicate flight" for the bulk-creation path.
 *
 * NOT used during commit. Commit-time integrity flows through Form Request
 * validation and the LintRunner (L4 + L5); this service exists purely to
 * power the typing-time UI check.
 */
final class DuplicateChecker
{
    /**
     * Find existing-flight collisions for each submitted row.
     *
     * One round-trip: pull every non-owner flight in the candidate
     * (airline_id, flight_number) space — bounded by the batch row cap — then
     * narrow in-memory by route_code/route_leg null-equivalence. The airline
     * relation is eager-loaded so the Flight::ident accessor (which reads
     * airline->code) stays free of N+1.
     *
     * @param  array<int, array<string, mixed>>                                                                 $rows Submitted rows; each row MUST carry
     *                                                                                                                airline_id and flight_number, and MAY
     *                                                                                                                carry route_code / route_leg.
     * @return array<int, array{index: int, existing_flight_id: string, ident: string, conflict_field: string}> Keyed by submitted row index.
     */
    public function check(array $rows): array
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
            ->with('airline:id,iata,icao')
            ->whereIn('airline_id', $airlineIds)
            ->whereIn('flight_number', $flightNumbers)
            ->whereNull('owner_type')
            ->get(['id', 'airline_id', 'flight_number', 'route_code', 'route_leg']);

        $byKey = $this->indexByStrictKey($existing);

        $duplicates = [];
        foreach ($rows as $index => $row) {
            $key = $this->dupKey(
                airlineId: $row['airline_id'] ?? null,
                flightNumber: $row['flight_number'] ?? null,
                routeCode: $row['route_code'] ?? null,
                routeLeg: $row['route_leg'] ?? null,
            );

            $hit = $byKey[$key] ?? null;
            if ($hit === null) {
                continue;
            }

            $duplicates[$index] = [
                'index'              => $index,
                'existing_flight_id' => $hit->id,
                'ident'              => $hit->ident,
                // The strict-key match is the full 4-tuple, but flight_number
                // is the operationally meaningful field surfaced to the admin
                // (airline scoping is implicit, route_code/leg are usually
                // null). UI renders this as "flight_number 1234 is taken".
                'conflict_field' => 'flight_number',
            ];
        }

        return $duplicates;
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

    /**
     * @param  Collection<int, Flight> $existing
     * @return array<string, Flight>
     */
    private function indexByStrictKey(Collection $existing): array
    {
        $byKey = [];
        foreach ($existing as $flight) {
            $key = $this->dupKey(
                airlineId: $flight->airline_id,
                flightNumber: $flight->flight_number,
                routeCode: $flight->route_code,
                routeLeg: $flight->route_leg,
            );
            $byKey[$key] = $flight;
        }

        return $byKey;
    }

    private function dupKey(
        mixed $airlineId,
        mixed $flightNumber,
        mixed $routeCode,
        mixed $routeLeg,
    ): string {
        return implode('|', [
            (string) ($airlineId ?? ''),
            (string) ($flightNumber ?? ''),
            $this->normalize($routeCode),
            $this->normalize($routeLeg),
        ]);
    }

    /**
     * Collapse null / empty-string / zero-equivalent values to a single
     * sentinel so the strict-key match treats them as the same "absent" value.
     * Matches L5's normalization and the legacy isFlightDuplicate semantics
     * where stored values may be NULL, '', or 0 depending on history.
     */
    private function normalize(mixed $value): string
    {
        if (in_array($value, [null, '', 0, '0'], true)) {
            return '∅';
        }

        return (string) $value;
    }
}
