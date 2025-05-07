<?php

namespace App\Console\Cron\Backups;

use App\Contracts\CronCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BackupRun extends CronCommand
{
    protected $signature = 'cron:backup-run';

    protected $description = 'Create a new backup';

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent(): void
    {
        Artisan::call('backup:run');

        $output = trim(Artisan::output());
        if ($output !== '' && $output !== '0') {
            Log::info($output);
        }
    }
}
