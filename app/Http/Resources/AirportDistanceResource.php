<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use Illuminate\Http\Request;
use Override;

class AirportDistanceResource extends Resource
{
    #[Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);
        $res['distance'] = $res['distance']->getResponseUnits();

        return $res;
    }
}
