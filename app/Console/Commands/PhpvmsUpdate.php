<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\PendingWork;
use App\Features\Setup\Support\SetupRunState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Brings the database current, the same way for an install and an update.
 * The container entrypoint runs it, and workers poll `--check` to wait for it.
 */
#[Signature('phpvms:update
    {--check : Only report whether the database is current; exit 0 when it is}
    {--wait=0 : Seconds to wait for another run to release the lock}
    {--db-timeout=30 : Seconds to wait for the database to answer}')]
#[Description('Run migrations, seeds and data migrations until the database is current')]
class PhpvmsUpdate extends Command
{
    public function handle(DetectPendingWork $detect, RunSetup $update): int
    {
        $pending = $this->detect($detect, (int) $this->option('db-timeout'));

        if (!$pending instanceof PendingWork) {
            $this->components->error('The database did not answer.');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            return !$pending->blocksPanel() ? self::SUCCESS : self::FAILURE;
        }

        if ($pending->isCurrent()) {
            $this->components->info('The database is current.');

            return self::SUCCESS;
        }

        $outcome = $this->runWaiting($detect, $update, $pending->freshInstall, (int) $this->option('wait'));

        // Another process may have finished the run; check before calling the database current.
        if ($outcome === RunOutcome::NothingRequested && $this->detect($detect, (int) $this->option('db-timeout'))?->isCurrent() !== true) {
            $this->components->error('The database is not current.');

            return self::FAILURE;
        }

        if ($outcome === RunOutcome::Busy) {
            $this->components->error('Another update is already running.');

            return self::FAILURE;
        }

        if ($outcome === RunOutcome::Failed) {
            $this->components->error($this->failureMessage());

            return self::FAILURE;
        }

        $this->components->info('The database is current.');

        return self::SUCCESS;
    }

    /**
     * Request and run, retrying every second while another process holds the lock.
     */
    private function runWaiting(DetectPendingWork $detect, RunSetup $update, bool $fresh, int $wait): RunOutcome
    {
        $deadline = time() + $wait;

        while (true) {
            $outcome = $update->requestAndRun('cli', $fresh, function (string $line): void {
                $this->line($line);
            });

            if ($outcome !== RunOutcome::Busy || time() >= $deadline) {
                return $outcome;
            }

            sleep(1);

            // The process that held the lock may have finished the work; a new request would redo every step.
            if ($this->detect($detect, (int) $this->option('db-timeout'))?->isCurrent() === true) {
                return RunOutcome::NothingRequested;
            }
        }
    }

    private function failureMessage(): string
    {
        $run = app(SetupRunState::class)->read();

        if ($run === null) {
            return 'The update failed.';
        }

        $failed = null;

        foreach ($run->steps as $name => $step) {
            if ($step['status'] === StepStatus::Failed) {
                $failed = $name;
            }
        }

        return 'The update failed'.($failed !== null ? ' at '.$failed : '').': '.($run->error ?? 'unknown error');
    }

    /**
     * Ask what is pending, retrying every second until the database answers.
     */
    private function detect(DetectPendingWork $detect, int $timeout): ?PendingWork
    {
        $deadline = time() + $timeout;

        while (true) {
            try {
                return $detect->handle();
            } catch (Throwable $throwable) {
                if (time() >= $deadline) {
                    $this->components->error($throwable->getMessage());

                    return null;
                }

                sleep(1);
            }
        }
    }
}
