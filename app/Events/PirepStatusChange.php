<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\Pirep;

/**
 * Status change like Boarding, Taxi, etc
 */
class PirepStatusChange extends Event
{
    public function __construct(public Pirep $pirep) {}
}
