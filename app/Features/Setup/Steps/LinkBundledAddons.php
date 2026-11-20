<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Addons\Support\BundledAddonLinker;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;

final readonly class LinkBundledAddons implements Step
{
    public function __construct(private BundledAddonLinker $linker) {}

    public function step(): SetupStep
    {
        return SetupStep::LinkAddons;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        foreach ($this->linker->link() as $name => $result) {
            ($ctx->log)("{$name}: {$result}");
        }
    }
}
