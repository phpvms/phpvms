<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class AirportDistance extends Resource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        $res = parent::toArray($request);
        $res['distance'] = $res['distance']->getResponseUnits();

        return $res;
    }
}
