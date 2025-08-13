<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use Illuminate\Http\Request;

class Rank extends Resource
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
            'subfleets' => Subfleet::collection($this->whenLoaded('subfleets')),
        ];
    }
}
