<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Enums\NavaidType;
use Illuminate\Http\Request;

class NavdataResource extends Resource
{
    #[\Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);

        // Some details about the navaid type
        $type = [
            'type' => $res['type'],
            'name' => NavaidType::from($res['type'])->getLabel(),
        ];

        $res['type'] = $type;

        return $res;
    }
}
