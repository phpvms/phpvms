<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use App\Enums\FlightType;
use App\Models\Subfleet;
use Illuminate\Http\Request;
use Override;

/**
 * Wire shape returned by /admin/route-forge/api/subfleets for a single
 * subfleet, sized for the RouteForge form's subfleet picker.
 *
 * Returns only the fields the client needs to render the picker and feed
 * lint computations (range mismatch L2, type mismatch L2b, capacity L1).
 * route_types is normalized to a list of FlightType `value` codes (e.g.
 * ["J","F","C"]); null means "unrestricted" (compatible with any flight
 * type). aircraft_count uses the Subfleet::aircraft relation which already
 * filters to AircraftStatus::ACTIVE, so the count is active-only without
 * an extra query path.
 *
 * @mixin Subfleet
 */
final class RouteForgeSubfleetResource extends Resource
{
    #[Override]
    public function toArray(Request $request): array
    {
        /** @var Subfleet $subfleet */
        $subfleet = $this->resource;

        return [
            'id'             => $subfleet->id,
            'name'           => $subfleet->name,
            'type'           => $subfleet->type,
            'cruise_speed'   => $subfleet->cruise_speed,
            'max_range_nm'   => $subfleet->max_range_nm,
            'route_types'    => $subfleet->route_types?->map(fn (FlightType $t): string => $t->value)->values()->all(),
            'aircraft_count' => (int) ($subfleet->aircraft_count ?? $subfleet->aircraft->count()),
        ];
    }
}
