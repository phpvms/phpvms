<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;

/**
 * This event is dispatched when the weekly cron is run
 * It happens after all of the default nightly tasks
 */
class CronWeekly extends Event {}
