<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use Illuminate\Http\Request;

/**
 * Wire shape returned by /admin/route-forge/api/airline-stats.
 *
 * Drives the L1 capacity hint in the client (existing flight count vs the
 * batch the user is composing) plus the form's "hub airports" affordance
 * that pre-fills origin pickers. home_airport is null in v1 — phpvms has no
 * airline-level home concept, only subfleet-level `hub_id`; the field stays
 * in the wire shape so the client doesn't have to feature-detect.
 *
 * The resource's $this->resource is the plain associative array assembled
 * by the controller (no Eloquent model wraps these aggregates).
 */
final class AirlineStatsResource extends Resource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $stats */
        $stats = $this->resource;

        return [
            'existing_active_flights_count' => (int) ($stats['existing_active_flights_count'] ?? 0),
            'hub_airports'                  => array_values($stats['hub_airports'] ?? []),
            'home_airport'                  => $stats['home_airport'] ?? null,
        ];
    }
}
