<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final class RefreshAddonCache implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::AddonCache;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        if (Artisan::call('addons:cache') !== 0) {
            throw new RuntimeException('addons:cache failed: '.trim(Artisan::output()));
        }
    }
}
