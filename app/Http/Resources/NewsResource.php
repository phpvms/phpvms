<?php

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
     * @param  Request $request
     * @return array
     */
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['user'] = [
            'id'   => $this->user->id,
            'name' => $this->user->name_private,
        ];

        return $res;
    }
}
