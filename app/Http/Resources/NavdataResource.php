<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Enums\NavaidType;

class NavdataResource extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);

        // Some details about the navaid type
        $type = [
            'type' => $res['type'],
            'name' => NavaidType::label($res['type']),
        ];

        $res['type'] = $type;

        return $res;
    }
}
