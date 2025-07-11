<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronFifteenMinute;

/**
 * The actual cron tasks are in app/Cron
 */
class FifteenMinute extends CronCommand
{
    protected $signature = 'cron:fifteen';

    protected $description = 'Run the 15 minute cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent(): void
    {
        event(new CronFifteenMinute());
    }
}
