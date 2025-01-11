<?php

namespace App\Http\Resources;

/**
 * @mixin \App\Models\Subfleet
 */
class BidSubfleet extends Subfleet
{
    protected $aircraft;

    protected $fares;

    public function __construct($resource, $aircraft, $fares)
    {
        parent::__construct($resource);

        $this->aircraft = $aircraft;
        $this->fares = $fares;
    }

    public function toArray($request)
    {
        $res = [];
        $res['airline_id'] = $this->airline_id;
        $res['hub_id'] = $this->hub_id;
        $res['type'] = $this->type;
        $res['simbrief_type'] = $this->simbrief_type;
        $res['name'] = $this->name;
        $res['fuel_type'] = $this->fuel_type;
        $res['cost_block_hour'] = $this->cost_block_hour;
        $res['cost_delay_minute'] = $this->cost_delay_minute;
        $res['ground_handling_multiplier'] = $this->ground_handling_multiplier;
        $res['cargo_capacity'] = $this->cargo_capacity;
        $res['fuel_capacity'] = $this->fuel_capacity;
        $res['gross_weight'] = $this->gross_weight;

        $res['fares'] = Fare::collection($this->fares);

        // There should only be one aircraft tied to a bid subfleet, wrap in a collection
        $res['aircraft'] = Aircraft::collection([$this->aircraft]);

        return $res;
    }
}
