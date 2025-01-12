<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronHourly;

/**
 * This just calls the CronHourly event, so all of the
 * listeners, etc can just be called to run those tasks.
 */
class Hourly extends CronCommand
{
    protected $signature = 'cron:hourly';

    protected $description = 'Run the hourly cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent()
    {
        event(new CronHourly());
    }
}
