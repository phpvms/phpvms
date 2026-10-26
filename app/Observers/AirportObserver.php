<?php

namespace App\Observers;

use App\Models\Airport;

/**
 * Make sure that the fields are properly capitalized
 */
class AirportObserver
{
    public function creating(Airport $airport): void
    {
        if (filled($airport->iata)) {
            $airport->iata = strtoupper(trim((string) $airport->iata));
        }

        $airport->icao = strtoupper(trim($airport->icao));

        // An airport id is its own value, not a second copy of the ICAO -- it
        // just has no other source yet. Seed it from the ICAO only when the
        // caller gave us nothing, so a caller that supplies an id keeps it.
        if (blank($airport->id)) {
            $airport->id = $airport->icao;
        }
    }

    public function updating(Airport $airport): void
    {
        if (filled($airport->iata)) {
            $airport->iata = strtoupper(trim((string) $airport->iata));
        }

        $airport->icao = strtoupper(trim($airport->icao));
    }
}
