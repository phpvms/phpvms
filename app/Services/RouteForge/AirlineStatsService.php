<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Models\Airline;

/**
 * Computes the airline-wide stats snapshot consumed by `/airline-stats`
 * and embedded in the LintContext for rules that need batch-vs-fleet
 * counts (L1 capacity check primarily).
 *
 * Single method, no state. Kept as a service rather than a model accessor
 * so the `Airline` Eloquent model stays free of RouteForge-specific
 * concerns — phpvms has no other consumer of this snapshot today.
 */
final class AirlineStatsService
{
    /**
     * Build the snapshot for one airline.
     *
     * - `existing_active_flights_count`: enabled, non-owner flights for the
     *   airline. The denominator the L1 capacity hint compares against.
     * - `hub_airports`: distinct `hub_id` values from the airline's
     *   subfleets. phpvms has no airline-level hub list; hubs are
     *   subfleet-level via `Subfleet::home`.
     * - `home_airport`: always `null` in v1; no airline-level home exists.
     *
     * @return array{existing_active_flights_count: int, hub_airports: list<string>, home_airport: string|null}
     */
    public function buildFor(Airline $airline): array
    {
        $existingActive = $airline->flights()
            ->where('enabled', true)
            ->whereNull('owner_type')
            ->count();

        /** @var list<string> $hubIcaos */
        $hubIcaos = $airline->subfleets()
            ->whereNotNull('hub_id')
            ->distinct()
            ->pluck('hub_id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        return [
            'existing_active_flights_count' => $existingActive,
            'hub_airports'                  => $hubIcaos,
            'home_airport'                  => null,
        ];
    }
}
