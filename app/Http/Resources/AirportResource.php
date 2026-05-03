<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Airport;

/**
 * @mixin Airport
 */
class AirportResource extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);

        if (!empty($this->description)) {
            $res['description'] = $this->description;
        }

        return $res;
    }
}
