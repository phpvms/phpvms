<?php

declare(strict_types=1);

namespace App\Features\Tour\Enums;

/**
 * The lifecycle of one pilot's run through a tour.
 *
 * Every value but `InProgress` is terminal — a run is never reopened, and the
 * row is never deleted. A pilot who wants the tour again starts a new run.
 *
 * `Cancelled` covers every way a run ends early that someone chose: the pilot
 * quit, a leg diverted or was rejected, the pilot was removed. `Expired` is the
 * one nobody chose — `bids.expire_time` swept the run up.
 */
enum TourStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
