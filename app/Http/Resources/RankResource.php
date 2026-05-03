<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Rank;
use Illuminate\Http\Request;

/**
 * @mixin Rank
 */
class RankResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'name'      => $this->name,
            'subfleets' => SubfleetResource::collection($this->whenLoaded('subfleets')),
        ];
    }
}
