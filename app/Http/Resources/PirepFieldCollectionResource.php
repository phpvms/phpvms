<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Override;

class PirepFieldCollectionResource extends ResourceCollection
{
    #[Override]
    public function toArray($request)
    {
        $res = [];
        foreach ($this->collection as $field) {
            $res[$field->name] = $field->value;
        }

        return $res;
    }
}
