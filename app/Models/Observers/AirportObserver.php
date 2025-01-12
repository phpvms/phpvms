<?php

namespace App\Models\Observers;

use App\Models\Airport;

/**
 * Make sure that the fields are properly capitalized.
 */
class AirportObserver
{
    public function creating(Airport $airport): void
    {
        if (filled($airport->iata)) {
            $airport->iata = strtoupper(trim($airport->iata));
        }

        $airport->icao = strtoupper(trim($airport->icao));
        $airport->id = $airport->icao;
    }

    public function updating(Airport $airport): void
    {
        if (filled($airport->iata)) {
            $airport->iata = strtoupper(trim($airport->iata));
        }

        $airport->icao = strtoupper(trim($airport->icao));
        $airport->id = $airport->icao;
    }
}
