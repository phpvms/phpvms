<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Support\Units\Distance;
use App\Support\Units\Fuel;

/**
 * @mixin \App\Models\Acars
 */
class Acars extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        $res = parent::toArray($request);

        // Set these to the response units
        $distance = !empty($res['distance']) ? $res['distance'] : 0;
        $distance = Distance::make($distance, config('phpvms.internal_units.distance'));
        $res['distance'] = $distance->getResponseUnits();

        $fuel = !empty($res['fuel']) ? $res['fuel'] : 0;
        $fuel = Fuel::make($fuel, config('phpvms.internal_units.fuel'));
        $res['fuel'] = $fuel->getResponseUnits();

        $res['pirep'] = Pirep::make($this->whenLoaded('pirep'));

        return $res;
    }
}
