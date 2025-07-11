<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Services\VersionService;
use Illuminate\Support\Facades\Log;

class NewVersionCheck extends Listener
{
    public function __construct(private readonly VersionService $versionSvc) {}

    /**
     * Set any users to being on leave after X days
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Nightly: Checking for new version');
        $this->versionSvc->isNewVersionAvailable();
    }
}
