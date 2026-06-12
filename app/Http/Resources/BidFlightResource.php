<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\SimBriefResource as SimbriefResource;
use App\Models\Flight;
use Illuminate\Http\Request;
use Override;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

/**
 * @mixin Flight
 */
class BidFlightResource extends FlightResource
{
    /**
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $res = parent::toArray($request);

        if ($this->whenLoaded('simbrief')) {
            unset($res['subfleets']);
            $res['simbrief'] = new SimbriefResource($this->simbrief);
        } else {
            unset($res['simbrief']);
            $res['subfleets'] = SubfleetResource::collection($this->whenLoaded('subfleets'));
        }

        $res['fields'] = $this->setFields();

        return $res;
    }
}
