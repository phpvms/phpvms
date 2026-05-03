<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Bid;

/**
 * @mixin Bid
 */
class BidResource extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['flight'] = new BidFlightResource($this->flight);

        return $res;
    }
}
