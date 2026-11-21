<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final class MaintenanceUp implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::MaintenanceUp;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('The site did not leave maintenance mode');
        }
    }
}
