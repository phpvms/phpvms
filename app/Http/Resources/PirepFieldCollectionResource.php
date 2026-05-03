<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PirepFieldCollectionResource extends ResourceCollection
{
    public function toArray($request)
    {
        $res = [];
        foreach ($this->collection as $field) {
            $res[$field->name] = $field->value;
        }

        return $res;
    }
}
