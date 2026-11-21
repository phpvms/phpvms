<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\MigrationRunner;
use App\Features\Setup\Support\StepContext;
use Illuminate\Database\Migrations\Migrator;

final readonly class RunDataMigrations implements Step
{
    public function __construct(
        private MigrationPaths $paths,
        private MigrationRunner $runner,
    ) {}

    public function step(): SetupStep
    {
        return SetupStep::DataMigrate;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        // The package's `migrator.data` has no event dispatcher, so it would report no progress.
        $migrator = new Migrator(app('migration.data.repository'), app('db'), app('files'), app('events'));

        $this->runner->run($migrator, $this->paths->data(), $ctx);
    }
}
