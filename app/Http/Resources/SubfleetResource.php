<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Subfleet;
use Illuminate\Http\Request;

/**
 * @mixin Subfleet
 */
class SubfleetResource extends Resource
{
    #[\Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);
        $res['fares'] = FareResource::collection($this->fares);
        $res['aircraft'] = AircraftResource::collection($this->aircraft);

        return $res;
    }
}
