<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\Stats
 */
class Stats extends Resource
{
    public function toArray($request)
    {
        return [
            'flights'         => $this->flights,
            'total_time'      => $this->flight_time,
            'average_time'    => $this->average_flight_time,
            'average_score'   => number_format($this->average_score),
            'balance'         => $this->balance,
            'average_fuel'    => number_format($this->average_fuel_used / 2.20462262185).' Kg',
            'average_landing' => number_format($this->average_landing_rate),
        ];
    }
}
