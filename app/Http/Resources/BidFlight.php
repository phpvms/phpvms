<?php

namespace App\Http\Resources;

use App\Http\Resources\SimBrief as SimbriefResource;

/**
 * @mixin \App\Models\Flight
 */
class BidFlight extends Flight
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \PhpUnitsOfMeasure\Exception\NonNumericValue
     * @throws \PhpUnitsOfMeasure\Exception\NonStringUnitName
     *
     * @return array
     */
    public function toArray($request)
    {
        $res = parent::toArray($request);

        if ($this->whenLoaded('simbrief')) {
            unset($res['subfleets']);
            $res['simbrief'] = new SimbriefResource($this->simbrief);
        } else {
            unset($res['simbrief']);
            $res['subfleets'] = Subfleet::collection($this->whenLoaded('subfleets'));
        }

        $res['fields'] = $this->setFields();

        return $res;
    }
}
