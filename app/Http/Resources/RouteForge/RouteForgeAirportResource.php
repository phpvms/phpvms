<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Http\Resources\AirportResource;
use App\Models\Airport;
use Illuminate\Http\Request;

/**
 * Admin RouteForge variant of AirportResource.
 *
 * Extends the public AirportResource and conditionally appends two computed
 * fields:
 *
 *   - distance_from_origin_nm: haversine distance (nautical miles) from the
 *     `near` ICAO supplied as a query parameter to /preview-airports.
 *   - in_subfleet_range: true iff `distance_from_origin_nm` is ≤ the
 *     `max_range_nm` query parameter.
 *
 * Both values are stamped onto the Airport model instance by
 * RouteForgeController::previewAirports() AFTER the paginator runs, as
 * dynamic attributes. This keeps the public AirportResource and shared
 * AirportSearchQueryV1 contracts unchanged — admin-only fields surface only
 * when the caller hits this resource path.
 *
 * Reads of the stamped attributes use `getAttribute()` so PHPStan sees the
 * Eloquent API rather than a phantom property access. The controller stamped
 * via `setAttribute()`; parent::toArray() also surfaces them through
 * Eloquent's attribute serialization, but the explicit read keeps the wire
 * contract documented and lets future field-shape changes (e.g. unit
 * conversion) live in one place.
 *
 * @mixin Airport
 */
final class RouteForgeAirportResource extends AirportResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $res = parent::toArray($request);

        $distance = $this->resource->getAttribute('distance_from_origin_nm');
        if ($distance !== null) {
            $res['distance_from_origin_nm'] = $distance;
        }

        $inRange = $this->resource->getAttribute('in_subfleet_range');
        if ($inRange !== null) {
            $res['in_subfleet_range'] = $inRange;
        }

        return $res;
    }
}
