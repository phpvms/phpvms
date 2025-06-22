<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronWeekly;

/**
 * This just calls the CronWeekly event, so all of the
 * listeners, etc can just be called to run those tasks.
 *
 * The actual cron tasks are in app/Cron
 */
class Weekly extends CronCommand
{
    protected $signature = 'cron:weekly';

    protected $description = 'Run the weekly cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent(): void
    {
        event(new CronWeekly());
    }
}
