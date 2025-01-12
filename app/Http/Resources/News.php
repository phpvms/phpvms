<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\News
 */
class News extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
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
