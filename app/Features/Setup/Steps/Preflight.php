<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\Requirements;
use App\Features\Setup\Support\StepContext;
use RuntimeException;

final class Preflight implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::Preflight;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        $problems = new Requirements()->failures();

        // The down state must be on disk so web, cron and workers all see it.
        if (config('app.maintenance.driver') !== 'file') {
            $problems[] = 'The maintenance driver must be "file" so the down state is on disk for web, cron and workers';
        }

        if ($problems !== []) {
            throw new RuntimeException(implode("\n", $problems));
        }
    }
}
