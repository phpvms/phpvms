<?php

namespace App\Observers;

use App\Models\Aircraft;
use App\Support\ICAO;
use Exception;

class AircraftObserver
{
    /**
     * @throws Exception
     */
    public function creating(Aircraft $aircraft): void
    {
        // Trimmed, not folded: `airport_id` holds an airport id, and an id is
        // matched as it was stored. Upper-casing it was safe only while an
        // airport id was its ICAO -- it would mangle a generated one.
        if (!empty($aircraft->airport_id)) {
            $aircraft->airport_id = trim((string) $aircraft->airport_id);
        }

        if (empty($aircraft->hex_code)) {
            $aircraft->hex_code = ICAO::createHexCode();
        }
    }
}
