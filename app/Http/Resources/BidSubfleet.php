<?php

namespace App\Http\Resources;

/**
 * @mixin \App\Models\Subfleet
 *
 * @property \App\Models\Aircraft $aircraft
 * @property \App\Models\Fare[]   $fares
 */
class BidSubfleet extends Subfleet
{
    public function toArray($request)
    {
        return [
            'airline_id'                 => $this->airline_id,
            'hub_id'                     => $this->hub_id,
            'type'                       => $this->type,
            'simbrief_type'              => $this->simbrief_type,
            'name'                       => $this->name,
            'fuel_type'                  => $this->fuel_type,
            'cost_block_hour'            => $this->cost_block_hour,
            'cost_delay_minute'          => $this->cost_delay_minute,
            'ground_handling_multiplier' => $this->ground_handling_multiplier,
            'cargo_capacity'             => $this->cargo_capacity,
            'fuel_capacity'              => $this->fuel_capacity,
            'gross_weight'               => $this->gross_weight,
            'fares'                      => Fare::collection($this->fares),
            // There should only be one aircraft tied to a bid subfleet, wrap in a collection
            'aircraft' => Aircraft::collection([$this->aircraft]),
        ];
    }
}
