<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Award;

/**
 * @mixin Award
 */
class AwardResource extends Resource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'image'       => $this->image,
        ];
    }
}
