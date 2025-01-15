<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use Illuminate\Support\Facades\Artisan;

/**
 * This just calls the CronHourly event, so all of the
 * listeners, etc can just be called to run those tasks
 */
class JobQueue extends CronCommand
{
    protected $signature = 'cron:queue';

    protected $description = 'Run the cron queue tasks';

    protected $schedule;

    public function handle(): void
    {
        $this->callEvent();

        $queueOutput = trim(Artisan::output());
        if (!empty($queueOutput)) {
            $this->info($queueOutput);
        }
    }

    public function callEvent()
    {
        Artisan::call('queue:cron');
    }
}
