<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\Pirep;

class PirepDiverted extends Event
{
    public function __construct(public Pirep $pirep) {}
}
