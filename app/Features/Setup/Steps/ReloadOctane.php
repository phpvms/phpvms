<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final class ReloadOctane implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::OctaneReload;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        // Octane registers its commands only under the console, so a web-run engine cannot call them.
        if (!app()->runningInConsole() || !array_key_exists('octane:status', Artisan::all())) {
            ($ctx->log)('Octane commands are not available, skipping reload');

            return;
        }

        // Exit 0 means a server is running; before Octane starts (Docker) there is nothing to reload.
        if (Artisan::call('octane:status') !== 0) {
            ($ctx->log)('Octane is not running, skipping reload');

            return;
        }

        if (Artisan::call('octane:reload') !== 0) {
            throw new RuntimeException('octane:reload failed: '.trim(Artisan::output()));
        }
    }
}
