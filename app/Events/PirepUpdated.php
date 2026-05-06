<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;
use App\Models\Pirep;

class PirepUpdated extends Event
{
    public function __construct(public Pirep $pirep) {}
}
