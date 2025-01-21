<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\Pirep;

/**
 * Status change like Boarding, Taxi, etc
 */
class PirepStatusChange extends Event
{
    public Pirep $pirep;

    public function __construct(Pirep $pirep)
    {
        $this->pirep = $pirep;
    }
}
