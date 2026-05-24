<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Flight;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use Illuminate\Support\Collection;

/**
 * L5 — Per-row collision with existing non-owner flight in the DB.
 *
 * Bulk-queries every flight in the airlines covered by the batch matching the
 * strict 4-tuple `(airline_id, flight_number, route_code, route_leg)` scoped
 * to `owner_type IS NULL`. The query MUST be a single round-trip — N+1 would
 * be untenable at the 100-row cap. Owner-typed flights (charter/personal) do
 * not participate in the org-level flight-number namespace and are excluded
 * per the spec.
 */
final class L5ExistingDuplicate implements LintRule
{
    public function id(): string
    {
        return 'L5';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

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

        // One bulk query: pull every non-owner flight in the candidate airline
        // × flight_number space, then narrow in-memory by route_code/route_leg
        // null-equivalence. This stays cheap because (airline_id, flight_number)
        // is heavily indexed and the candidate set is bounded by the batch size.
        $existing = Flight::query()
            ->whereIn('airline_id', $airlineIds)
            ->whereIn('flight_number', $flightNumbers)
            ->whereNull('owner_type')
            ->get(['id', 'airline_id', 'flight_number', 'route_code', 'route_leg']);

        $byKey = $this->indexByStrictKey($existing);

        $issues = [];
        foreach ($ctx->rows as $index => $row) {
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

    private function normalize(mixed $value): string
    {
        if (in_array($value, [null, '', 0, '0'], true)) {
            return '∅';
        }

        return (string) $value;
    }
}
