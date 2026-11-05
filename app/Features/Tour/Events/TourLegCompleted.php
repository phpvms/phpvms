<?php

declare(strict_types=1);

namespace App\Features\Tour\Events;

use App\Contracts\Event;
use App\Features\Tour\Models\UserTour;
use App\Models\Flight;
use App\Models\Pirep;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Fired when a PIREP newly files one leg of a tour; for the last leg it fires before TourCompleted.
 */
class TourLegCompleted extends Event implements ShouldDispatchAfterCommit
{
    public function __construct(
        public UserTour $tour,
        public Pirep $pirep,
        public ?Flight $flight,
        public int $legNumber,
        public bool $isFinalLeg,
    ) {}
}
