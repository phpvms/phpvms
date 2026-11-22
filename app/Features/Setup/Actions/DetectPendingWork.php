<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\PendingWork;
use App\Features\Setup\Support\SetupRunState;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reports the work a run of {@see RunSetup} would do.
 *
 * Needs a reachable database.
 */
final readonly class DetectPendingWork
{
    public function __construct(
        private MigrationPaths $paths,
        private CoreSeeds $seeds,
        private SetupRunState $runState,
    ) {}

    public function handle(): PendingWork
    {
        $stateError = null;

        // An unreadable state file counts as an unfinished run: do not guess "done".
        try {
            $unfinished = ($this->runState->read()->status ?? RunStatus::Done) !== RunStatus::Done;
        } catch (Throwable $throwable) {
            $unfinished = true;
            $stateError = $throwable->getMessage();
        }

        return new PendingWork(
            freshInstall: !Schema::hasTable('migrations'),
            pendingMigrations: $this->paths->pendingSchema(),
            pendingDataMigrations: $this->paths->pendingData(),
            pendingCoreSeeds: $this->seeds->pending(),
            runUnfinished: $unfinished,
            stateError: $stateError,
        );
    }
}
