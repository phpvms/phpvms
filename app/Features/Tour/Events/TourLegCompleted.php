<?php

declare(strict_types=1);

namespace App\Features\Tour\Events;

use App\Contracts\Event;
use App\Features\Tour\Models\UserTour;
use App\Models\Flight;
use App\Models\Pirep;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Fired when a PIREP newly files one leg of a tour; for the filing that completes the tour it fires before TourCompleted.
 *
 * `completesTour` is true when this filing completed the tour, which on an
 * out-of-order run need not be the route's last leg; compare `legNumber` with
 * `tour->legs_total` for that.
 */
class TourLegCompleted extends Event implements ShouldDispatchAfterCommit
{
    public function __construct(
        public UserTour $tour,
        public Pirep $pirep,
        public ?Flight $flight,
        public int $legNumber,
        public bool $completesTour,
    ) {}
}
