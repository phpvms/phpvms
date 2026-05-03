<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Subfleet;

/**
 * @mixin Subfleet
 */
class SubfleetResource extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['fares'] = FareResource::collection($this->fares);
        $res['aircraft'] = AircraftResource::collection($this->aircraft);

        return $res;
    }
}
