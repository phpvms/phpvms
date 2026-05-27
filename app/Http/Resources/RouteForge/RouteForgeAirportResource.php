<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Http\Resources\AirportResource;
use App\Models\Airport;
use App\Support\Geo;
use Illuminate\Http\Request;

/**
 * Admin RouteForge variant of AirportResource.
 *
 * Extends the public AirportResource and conditionally appends two
 * computed fields:
 *
 *   - `distance_from_origin_nm`: great-circle distance (nautical miles)
 *     from the `near` ICAO supplied to /preview-airports.
 *   - `in_subfleet_range`: true iff `distance_from_origin_nm` ≤ the
 *     `max_range_nm` query parameter.
 *
 * Decoration context is passed in via the request attribute bag:
 *
 *   - `$request->attributes->get('routeforge.origin')` — `?Airport` model
 *     with non-null lat/lon, or `null` when no `near` was supplied (or it
 *     didn't resolve).
 *   - `$request->attributes->get('routeforge.max_range_nm')` — `?int`, or
 *     `null` when the parameter was absent.
 *
 * `RouteForgeController::previewAirports()` stashes both on the global
 * `app('request')` instance (the Form Request's attribute bag does NOT
 * propagate — Laravel's FormRequest is a separate Request instance from
 * the container-bound `request` singleton the resource resolves during
 * serialization). The resource then computes distance per row via
 * `App\Support\Geo::haversineNm()`. The underlying `Airport` model's
 * attribute bag is left untouched — these fields exist only on the wire,
 * not on the Eloquent model.
 *
 * @mixin Airport
 */
final class RouteForgeAirportResource extends AirportResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $res = parent::toArray($request);

        /** @var ?Airport $origin */
        $origin = $request->attributes->get('routeforge.origin');
        if (!$origin instanceof Airport) {
            return $res;
        }

        if ($origin->lat === null || $origin->lon === null) {
            return $res;
        }

        if ($this->resource->lat === null || $this->resource->lon === null) {
            return $res;
        }

        $distance = Geo::haversineNm(
            latA: (float) $origin->lat,
            lonA: (float) $origin->lon,
            latB: (float) $this->resource->lat,
            lonB: (float) $this->resource->lon,
        );

        $res['distance_from_origin_nm'] = round($distance, 1);

        $maxRangeNm = $request->attributes->get('routeforge.max_range_nm');
        if (is_int($maxRangeNm)) {
            $res['in_subfleet_range'] = $distance <= $maxRangeNm;
        }

        return $res;
    }
}
