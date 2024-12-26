<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class Rank extends Resource
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
        return [
            'name'      => $this->name,
            'subfleets' => Subfleet::collection($this->whenLoaded('subfleets')),
        ];
    }
}
