<?php

namespace App\Console\Cron\Backups;

use App\Contracts\CronCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BackupClean extends CronCommand
{
    protected $signature = 'cron:backup-clean';

    protected $description = 'Clean up old backups';

    public function handle(): void
    {
        $this->callEvent();
    }

    public function callEvent(): void
    {
        Artisan::call('backup:clean');

        $output = trim(Artisan::output());
        if ($output !== '' && $output !== '0') {
            Log::info($output);
        }
    }
}
