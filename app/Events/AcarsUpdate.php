<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\Acars;
use App\Models\Pirep;

class AcarsUpdate extends Event
{
    public Pirep $pirep;

    public Acars $acars;

    public function __construct(Pirep $pirep, Acars $acars)
    {
        $this->pirep = $pirep;
        $this->acars = $acars;
    }
}
