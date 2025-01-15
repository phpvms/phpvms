<?php

namespace App\Console\Cron\Backups;

use App\Contracts\CronCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BackupMonitor extends CronCommand
{
    protected $signature = 'cron:backup-monitor';

    protected $description = 'Monitor backups health';

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent(): void
    {
        Artisan::call('backup:monitor');

        $output = trim(Artisan::output());
        if (!empty($output)) {
            Log::info($output);
        }
    }
}
