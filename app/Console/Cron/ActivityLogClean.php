<?php

namespace App\Console\Cron;

use App\Contracts\CronCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ActivityLogClean extends CronCommand
{
    protected $signature = 'cron:activitylog-clean';
    protected $description = 'Clean up old activity logs';

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent(): void
    {
        Artisan::call('activitylog:clean --force');

        $output = trim(Artisan::output());
        if (!empty($output)) {
            Log::info($output);
        }
    }
}
