<?php

namespace App\Models\Observers;

use App\Models\Aircraft;
use App\Support\ICAO;

class AircraftObserver
{
    /**
     * @throws \Exception
     */
    public function creating(Aircraft $aircraft): void
    {
        if (!empty($aircraft->airport_id)) {
            $aircraft->airport_id = strtoupper((string) $aircraft->airport_id);
        }

        if (empty($aircraft->hex_code)) {
            $aircraft->hex_code = ICAO::createHexCode();
        }
    }
}
