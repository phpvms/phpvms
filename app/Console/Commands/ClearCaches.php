<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use Nwidart\Modules\Facades\Module;

class ClearCaches extends Command
{
    protected $signature = 'phpvms:caches';

    protected $description = 'Clear all caches';

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        self::clearCaches();
    }

    public static function clearCaches()
    {
        self::clearBootstrapCache();
        self::clearModuleCache();
    }

    /**
     * Clear the bootstrap/cache dir.
     */
    private static function clearBootstrapCache() {}

    /**
     * Rescan for new modules.
     */
    private static function clearModuleCache()
    {
        Module::scan();
    }
}
