<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\PirepPosition;
use App\Support\Units\Distance;
use App\Support\Units\Fuel;
use Illuminate\Http\Request;
use Override;

/**
 * The live map's nested `position` object.
 *
 * Distance and fuel go out as the multi-unit objects the rest of the API uses,
 * the same way `AcarsResource` presents them, so a client reads
 * `position.distance.nmi` here exactly as it did when this came off `acars`.
 *
 * @mixin PirepPosition
 */
class PirepPositionResource extends Resource
{
    /**
     * @return array
     */
    #[Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);

        $distance = empty($res['distance']) ? 0 : $res['distance'];
        $res['distance'] = Distance::make($distance, config('phpvms.internal_units.distance'))->getResponseUnits();

        $fuel = empty($res['fuel_used']) ? 0 : $res['fuel_used'];
        $res['fuel_used'] = Fuel::make($fuel, config('phpvms.internal_units.fuel'))->getResponseUnits();

        return $res;
    }
}
