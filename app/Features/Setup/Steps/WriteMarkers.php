<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use App\Models\User;
use App\Support\InstallMarker;

final class WriteMarkers implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::Marker;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        // The marker closes the installer, so it waits for the first user.
        if (User::query()->exists()) {
            resolve(InstallMarker::class)->write();
        }
    }
}
