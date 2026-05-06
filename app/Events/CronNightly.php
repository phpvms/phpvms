<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;

/**
 * This event is dispatched when the daily cron is run
 * It happens after all of the default nightly tasks
 */
class CronNightly extends Event {}
