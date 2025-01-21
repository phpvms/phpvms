<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronNightly;

/**
 * This just calls the CronNightly event, so all of the
 * listeners, etc can just be called to run those tasks.
 *
 * The actual cron tasks are in app/Cron
 */
class Nightly extends CronCommand
{
    protected $signature = 'cron:nightly';

    protected $description = 'Run the nightly cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent()
    {
        event(new CronNightly());
    }
}
