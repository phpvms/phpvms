<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final class MaintenanceDown implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::MaintenanceDown;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        if (!app()->isDownForMaintenance()) {
            Artisan::call('down');
        }

        if (!app()->isDownForMaintenance()) {
            throw new RuntimeException('The site did not enter maintenance mode');
        }
    }
}
