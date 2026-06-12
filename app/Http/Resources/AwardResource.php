<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Award;
use Override;

/**
 * @mixin Award
 */
class AwardResource extends Resource
{
    #[Override]
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
