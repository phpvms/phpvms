<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\StepContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final readonly class SeedCore implements Step
{
    public function __construct(private CoreSeeds $seeds) {}

    public function step(): SetupStep
    {
        return SetupStep::Seed;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        if (!$ctx->fresh) {
            $this->seeds->sync($ctx->log);

            return;
        }

        if (Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]) !== 0) {
            throw new RuntimeException('db:seed failed: '.trim(Artisan::output()));
        }

        ($ctx->log)('Ran '.DatabaseSeeder::class);
    }
}
