<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronMonthly;

/**
 * This just calls the CronMonthly event, so all of the
 * listeners, etc can just be called to run those tasks
 *
 * The actual cron tasks are in app/Cron
 */
class Monthly extends CronCommand
{
    protected $signature = 'cron:monthly';

    protected $description = 'Run the monthly cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent()
    {
        event(new CronMonthly());
    }
}
