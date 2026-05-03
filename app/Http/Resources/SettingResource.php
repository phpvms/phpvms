<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Setting;

/**
 * @mixin Setting
 */
class SettingResource extends Resource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'name'        => $this->name,
            'value'       => $this->value,
            'group'       => $this->group,
            'order'       => $this->order,
            'description' => $this->description,
        ];
    }
}
