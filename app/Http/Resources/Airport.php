<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\Airport
 */
class Airport extends Resource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        $res = parent::toArray($request);

        if (!empty($this->description)) {
            $res['description'] = $this->description;
        }

        return $res;
    }
}
