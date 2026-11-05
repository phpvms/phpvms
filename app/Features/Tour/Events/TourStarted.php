<?php

declare(strict_types=1);

namespace App\Features\Tour\Events;

use App\Contracts\Event;
use App\Features\Tour\Models\UserTour;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Fired when a pilot starts a tour.
 */
class TourStarted extends Event implements ShouldDispatchAfterCommit
{
    public function __construct(public UserTour $tour) {}
}
