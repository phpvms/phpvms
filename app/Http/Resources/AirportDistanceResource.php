<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class AirportDistanceResource extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['distance'] = $res['distance']->getResponseUnits();

        return $res;
    }
}
