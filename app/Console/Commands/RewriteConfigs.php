<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Services\Installer\ConfigService;

/**
 * Command to rewrite the config files
 */
class RewriteConfigs extends Command
{
    protected $signature = 'phpvms:rewrite-configs';

    protected $description = 'Rewrite the config files';

    /**
     * Run dev related commands
     */
    public function handle()
    {
        /** @var ConfigService $configSvc */
        $configSvc = app(ConfigService::class);
        $configSvc->rewriteConfigFiles();
    }
}
