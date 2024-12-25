<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
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

        $fares = [];

        try {
            if (!empty($this->fare_data)) {
                $fare_data = json_decode($this->fare_data, true);
                foreach ($fare_data as $fare) {
                    $fares[] = new \App\Models\Fare($fare);
                }

                $fares = collect($fares);
            }
        } catch (\Exception $e) {
            // Invalid fare data
        }

        if (!($this->whenLoaded('aircraft') instanceof MissingValue)) {
            $data['subfleet'] = new BidSubfleet($this->aircraft->subfleet, $this->aircraft, $fares);
        }

        return $data;
    }
}
