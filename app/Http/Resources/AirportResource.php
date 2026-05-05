<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Airport;
use Illuminate\Http\Request;

/**
 * @mixin Airport
 */
class AirportResource extends Resource
{
    #[\Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);

        if (!empty($this->description)) {
            $res['description'] = $this->description;
        }

        return $res;
    }
}
