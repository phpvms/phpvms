<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\Subfleet
 */
class Subfleet extends Resource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        $res = parent::toArray($request);
        $res['fares'] = Fare::collection($this->fares);
        $res['aircraft'] = Aircraft::collection($this->aircraft);

        return $res;
    }
}
