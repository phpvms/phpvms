<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\Bid
 */
class Bid extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['flight'] = new BidFlight($this->flight);

        return $res;
    }
}
