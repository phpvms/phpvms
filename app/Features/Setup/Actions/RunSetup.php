<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\RunLog;
use App\Features\Setup\Support\RunSnapshot;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the requested steps in {@see SetupStep} order, one process at a time.
 * Done steps other than preflight, maintenance_down, cache_tables and maintenance_up are not re-run, so a failed run resumes.
 */
final readonly class RunSetup
{
    /**
     * @param iterable<Step> $steps
     */
    public function __construct(
        private SetupRunState $state,
        private SetupLock $lock,
        private iterable $steps,
        private UpgradeCompleted $completed,
    ) {}

    // Hidden context the web cron sets, so the scheduler leaves setup to the CLI cron.
    public const string WEB_CRON = 'phpvms.web_cron';

    /**
     * Record a run request. False when another process holds the lock.
     */
    public function request(string $by, bool $fresh, ?string $log = null): bool
    {
        if (!$this->lock->acquire(0)) {
            return false;
        }

        // The log line is written under the lock too, so it cannot overwrite a tick that has started.
        try {
            $this->state->request($by, $fresh);

            if ($log !== null) {
                $this->state->appendLog($log);
            }
        } finally {
            $this->lock->release();
        }

        return true;
    }

    /**
     * Request and run under one lock, so a cron tick cannot take the run in between.
     *
     * @param (Closure(string): void)|null $echo
     */
    public function requestAndRun(string $by, bool $fresh, ?Closure $echo = null): RunOutcome
    {
        if (!$this->lock->acquire(0)) {
            return RunOutcome::Busy;
        }

        try {
            $this->state->request($by, $fresh);

            return $this->run($echo);
        } finally {
            $this->lock->release();
        }
    }

    /**
     * @param (Closure(string): void)|null $echo
     */
    public function handle(?Closure $echo = null): RunOutcome
    {
        if (!$this->lock->acquire(0)) {
            return RunOutcome::Busy;
        }

        try {
            return $this->run($echo);
        } finally {
            $this->lock->release();
        }
    }

    /**
     * @param (Closure(string): void)|null $echo
     */
    private function run(?Closure $echo): RunOutcome
    {
        $snapshot = $this->state->read();

        if (!$snapshot instanceof RunSnapshot || !in_array($snapshot->status, [RunStatus::Requested, RunStatus::Running], true)) {
            return RunOutcome::NothingRequested;
        }

        $log = RunLog::for($snapshot->id);
        $line = function (string $line) use ($log, $echo): void {
            $log->write($line);
            $this->state->appendLog($line);

            if ($echo instanceof Closure) {
                $echo($line);
            }
        };
        $ctx = new StepContext($snapshot->fresh, $line, $this->state->progress(...));

        $bound = [];

        foreach ($this->steps as $step) {
            $bound[$step->step()->value] = $step;
        }

        $this->state->markRunning();

        foreach (SetupStep::cases() as $case) {
            // Re-checked every time: an operator may have brought the site up before a retry.
            // Innodb too: a database restored from the backup before a retry is MyISAM again.
            $always = in_array($case, [SetupStep::Preflight, SetupStep::MaintenanceDown, SetupStep::Innodb, SetupStep::CacheTables, SetupStep::MaintenanceUp], true);

            if (!$always && ($snapshot->steps[$case->value]['status'] ?? StepStatus::Pending) === StepStatus::Done) {
                continue;
            }

            $step = $bound[$case->value] ?? null;

            try {
                // applies() probes the database, so a throw there fails the step too.
                if ($step === null || !$step->applies($ctx)) {
                    $this->state->stepSkipped($case);

                    continue;
                }

                $this->state->stepStarted($case);
                $line('==> '.$case->value);
                $step->run($ctx);
            } catch (Throwable $throwable) {
                $error = $this->publicMessage($throwable);
                $line('Failed: '.$error);
                $this->state->stepFailed($case, $error);
                $this->state->finished(RunStatus::Failed);

                return RunOutcome::Failed;
            }

            $this->state->stepDone($case);
        }

        // Written first: a Done run without the file would leave /system/upgrade open.
        try {
            $this->completed->write();
        } catch (Throwable $throwable) {
            $error = $this->publicMessage($throwable);
            $line('Failed: '.$error);
            $this->state->finished(RunStatus::Failed, $error);

            return RunOutcome::Failed;
        }

        $this->state->finished(RunStatus::Done);

        return RunOutcome::Ran;
    }

    /**
     * The state file is shown to guests, so SQL and its bindings go to the log only.
     */
    private function publicMessage(Throwable $throwable): string
    {
        Log::error('Setup step failed: '.$throwable::class.': '.$throwable->getMessage(), ['exception' => $throwable]);

        $message = $throwable->getMessage();

        if ($throwable instanceof QueryException) {
            $cut = strpos($message, ' (Connection: ');

            if ($cut !== false) {
                $message = substr($message, 0, $cut);
            }
        }

        return $message;
    }
}
