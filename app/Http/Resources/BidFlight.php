<?php

namespace App\Http\Resources;

use App\Http\Resources\SimBrief as SimbriefResource;
use Illuminate\Http\Request;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

/**
 * @mixin \App\Models\Flight
 */
class BidFlight extends Flight
{
    /**
     * @param  Request $request
     * @return array
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
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
