<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\MigrationRunner;
use App\Features\Setup\Support\StepContext;
use Illuminate\Database\Migrations\Migrator;

/**
 * The migrator skips migrations already recorded, so a re-run resumes.
 */
final readonly class RunMigrations implements Step
{
    public function __construct(
        private MigrationPaths $paths,
        private MigrationRunner $runner,
    ) {}

    public function step(): SetupStep
    {
        return SetupStep::Migrate;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        $this->runner->run($migrator, $this->paths->schema(), $ctx);
    }
}
