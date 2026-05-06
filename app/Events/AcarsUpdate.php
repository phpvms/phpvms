<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;
use App\Models\Acars;
use App\Models\Pirep;

class AcarsUpdate extends Event
{
    public function __construct(public Pirep $pirep, public Acars $acars) {}
}
