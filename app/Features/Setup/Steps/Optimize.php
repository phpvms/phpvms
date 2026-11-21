<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\SetupCache;
use App\Features\Setup\Support\StepContext;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

final class Optimize implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::Optimize;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        // config:cache boots a second app, which replaces the global instances.
        $app = app();
        $building = SetupCache::$buildingConfig;
        SetupCache::$buildingConfig = true;

        try {
            $this->call('optimize:clear');

            // optimize:clear only flushed the array store this process uses; the site's own keeps 7.x entries.
            if (SetupCache::$realStore !== null) {
                Cache::store(SetupCache::$realStore)->flush();
            }

            // Not `optimize`: it would re-run addons:cache, which the addon_cache step just did.
            foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $command) {
                $this->call($command);
            }
        } finally {
            SetupCache::$buildingConfig = $building;
            Container::setInstance($app);
            Facade::setFacadeApplication($app);
        }

        // Child failures can be hidden, so check that the config cache exists.
        if (!is_file($app->getCachedConfigPath())) {
            throw new RuntimeException('The config cache was not written');
        }
    }

    private function call(string $command): void
    {
        if (Artisan::call($command) !== 0) {
            throw new RuntimeException($command.' failed: '.trim(Artisan::output()));
        }
    }
}
