<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronFiveMinute;

/**
 * This just calls the CronNightly event, so all of the
 * listeners, etc can just be called to run those tasks
 *
 * The actual cron tasks are in app/Cron
 */
class FiveMinute extends CronCommand
{
    protected $signature = 'cron:five';

    protected $description = 'Run the 5 minute cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent()
    {
        event(new CronFiveMinute());
    }
}
