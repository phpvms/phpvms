<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Fare;
use Exception;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin \App\Models\SimBrief
 */
class SimBrief extends Resource
{
    public function toArray($request)
    {
        $data = [
            'id'          => $this->id,
            'aircraft_id' => $this->aircraft_id,
            'url'         => url(route('api.flights.briefing', ['id' => $this->id])),
        ];

        $fares = collect();

        try {
            if (!empty($this->fare_data)) {
                $fare_data = json_decode($this->fare_data, true);
                foreach ($fare_data as $fare) {
                    $newFare = new Fare($fare);
                    $newFare->count = $fare['count'];
                    $fares->push($newFare);
                }
            }
        } catch (Exception $e) {
            // Invalid fare data
        }

        if (!($this->whenLoaded('aircraft') instanceof MissingValue)) {
            /** @var \stdClass $resource */
            $resource = (object) $this->aircraft->subfleet;
            $resource->aircraft = $this->aircraft->withoutRelations();
            $resource->fares = $fares;
            $data['subfleet'] = new BidSubfleet($resource);
        }

        return $data;
    }
}
