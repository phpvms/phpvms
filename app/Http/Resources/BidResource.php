<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Bid;
use Illuminate\Http\Request;

/**
 * @mixin Bid
 */
class BidResource extends Resource
{
    #[\Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);
        $res['flight'] = new BidFlightResource($this->flight);

        return $res;
    }
}
