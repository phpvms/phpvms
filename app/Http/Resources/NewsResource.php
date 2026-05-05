<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\News;
use Illuminate\Http\Request;

/**
 * @mixin News
 */
class NewsResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @return array
     */
    #[\Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);
        $res['user'] = [
            'id'   => $this->user->id,
            'name' => $this->user->name_private,
        ];

        return $res;
    }
}
