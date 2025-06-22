<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class Bid extends Resource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        $res = parent::toArray($request);
        $res['flight'] = new BidFlight($this->flight);

        return $res;
    }
}
