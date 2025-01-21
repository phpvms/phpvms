<?php

namespace App\Models\Observers;

use App\Models\Flight;

/**
 * Make sure that the fields are properly capitalized
 */
class FlightObserver
{
    public function creating(Flight $flight): void
    {
        $flight->dpt_airport_id = strtoupper(trim($flight->dpt_airport_id));
        $flight->arr_airport_id = strtoupper(trim($flight->arr_airport_id));
    }

    public function updating(Flight $flight): void
    {
        $flight->dpt_airport_id = strtoupper(trim($flight->dpt_airport_id));
        $flight->arr_airport_id = strtoupper(trim($flight->arr_airport_id));
    }
}
