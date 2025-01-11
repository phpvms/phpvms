<?php

namespace App\Events;

use App\Contracts\Event;

/**
 * This event is dispatched when the weekly cron is run
 * It happens after all of the default nightly tasks
 */
class CronWeekly extends Event
{
    public function __construct()
    {
    }
}
