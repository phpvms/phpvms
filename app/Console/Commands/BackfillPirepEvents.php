<?php

namespace App\Console\Commands;

use App\Enums\AcarsType;
use App\Features\Setup\Actions\RunPostUpgradeTasks;
use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Enums\PostUpgradeOutcome;
use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Features\Setup\PostUpgrade\BackfillPirepEvents as BackfillTask;
use App\Models\Acars;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

/**
 * Runs the flight-log post-upgrade task to completion; the update run's data_tasks step runs it too.
 */
#[AsCommand(name: 'phpvms:backfill-pirep-events', description: 'Backfill acars LOG rows into the pirep_events table')]
#[Signature('phpvms:backfill-pirep-events
                            {--delete : Purge the migrated acars LOG rows after a successful backfill}')]
class BackfillPirepEvents extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RunPostUpgradeTasks $runner, BackfillTask $task): int
    {
        // Runs on every container start: a finished copy must not touch acars.
        if ($runner->status(BackfillTask::KEY) === PostUpgradeStatus::Done) {
            $this->components->info('Flight logs already copied.');

            return $this->option('delete') ? $this->purge() : self::SUCCESS;
        }

        $before = $task->load();
        $bar = null;

        $error = null;

        try {
            $outcome = $runner->handle(
                progress: function (PostUpgradeTask $task, int $done, int $total) use (&$bar): void {
                    $bar ??= $this->output->createProgressBar();
                    $bar->setMaxSteps($total);
                    $bar->setProgress($done);
                },
                only: BackfillTask::KEY,
            );
        } catch (Throwable $throwable) {
            $outcome = null;
            $error = $throwable->getMessage();
        }

        if ($bar instanceof ProgressBar) {
            $bar->finish();
            $this->newLine();
        }

        if ($outcome === PostUpgradeOutcome::Busy) {
            $this->components->error('The flight log copy is already running in another process.');

            return self::FAILURE;
        }

        $after = $task->load();

        $this->components->info(sprintf(
            'Migrated %d row(s); %d referenced a missing telemetry row; %d row(s) skipped.',
            $after['copied'] - $before['copied'],
            $after['missing_telemetry'] - $before['missing_telemetry'],
            $after['skipped_count'] - $before['skipped_count'],
        ));

        if ($error !== null) {
            $this->components->error('The flight log copy failed: '.$error);

            return self::FAILURE;
        }

        return $this->option('delete') ? $this->purge() : $this->skipPurge();
    }

    private function skipPurge(): int
    {
        $this->components->warn('Purge skipped.');

        return self::SUCCESS;
    }

    /**
     * Purges the copied acars LOG rows, only once the copy is done.
     */
    private function purge(): int
    {
        if (app(RunPostUpgradeTasks::class)->status(BackfillTask::KEY) !== PostUpgradeStatus::Done) {
            $this->components->error('The flight log copy is not done; the acars LOG rows are kept.');

            return self::FAILURE;
        }

        if ($this->input->isInteractive() && !$this->confirm(
            'This will permanently delete the migrated acars LOG rows. This cannot be undone. Continue?'
        )) {
            return $this->skipPurge();
        }

        $purged = Acars::query()->ofType(AcarsType::LOG)->delete();
        $this->components->info(sprintf('Purged %d acars LOG row(s).', $purged));

        return self::SUCCESS;
    }
}
