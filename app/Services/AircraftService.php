<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Aircraft;
use App\Models\Enums\AircraftStatus;
use App\Models\Enums\PirepState;
use App\Models\Pirep;

class AircraftService extends Service
{
    /**
     * Recalculate all aircraft stats and hours
     */
    public function recalculateStats()
    {
        $allAircraft = Aircraft::where('status', AircraftStatus::ACTIVE)->get(); // TODO: Soft delete
        foreach ($allAircraft as $aircraft) {
            $pirep_time_total = Pirep::where('aircraft_id', $aircraft->id)
                ->where('state', PirepState::ACCEPTED)
                ->sum('flight_time');
            $aircraft->flight_time = $pirep_time_total;
            $aircraft->save();
        }
    }
}
