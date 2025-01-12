<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use App\Events\CronThirtyMinute;

/**
 * The actual cron tasks are in app/Cron.
 */
class ThirtyMinute extends CronCommand
{
    protected $signature = 'cron:thirty';

    protected $description = 'Run the 30 minute cron tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent()
    {
        event(new CronThirtyMinute());
    }
}
