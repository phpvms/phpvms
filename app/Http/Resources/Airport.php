<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\Airport
 */
class Airport extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);

        return $res;
    }
}
