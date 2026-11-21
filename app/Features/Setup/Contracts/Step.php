<?php

declare(strict_types=1);

namespace App\Features\Setup\Contracts;

use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Throwable;

interface Step
{
    public function step(): SetupStep;

    /**
     * False marks the step skipped.
     */
    public function applies(StepContext $ctx): bool;

    /**
     * @throws Throwable to fail the run
     */
    public function run(StepContext $ctx): void;
}
