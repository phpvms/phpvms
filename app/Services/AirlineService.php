<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Models\Airline;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\Subfleet;

class AirlineService extends Service
{
    /**
     * Create a new airline, and initialize the journal
     */
    public function createAirline(array $attr): Airline
    {
        $airline = Airline::create($attr);
        $airline->refresh();

        return $airline;
    }

    /**
     * Can the airline be deleted? Check if there are flights, etc associated with it
     */
    public function canDeleteAirline(Airline $airline): bool
    {
        if (Pirep::where('airline_id', $airline->id)->exists()) {
            return false;
        }

        if (Flight::where('airline_id', $airline->id)->exists()) {
            return false;
        }

        return !Subfleet::where('airline_id', $airline->id)->exists();
    }
}
