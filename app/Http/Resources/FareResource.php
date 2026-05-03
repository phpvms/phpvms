<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Fare;

/**
 * @mixin Fare
 *
 * @property int|null $count
 */
class FareResource extends Resource
{
    public function toArray($request)
    {
        return [
            'id'       => $this->id,
            'code'     => $this->code,
            'name'     => $this->name,
            'capacity' => $this->capacity,
            'cost'     => $this->cost,
            'count'    => ($this->count >= 0) ? $this->count : 0,
            'price'    => $this->price,
            'type'     => $this->type,
            'notes'    => $this->notes,
            'active'   => $this->active,
        ];
    }
}
