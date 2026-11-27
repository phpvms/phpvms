<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepOutcome;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\CachedPendingWork;
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
        ?Closure $webUnderOctane = null,
    ) {
        // Octane sets $_SERVER['LARAVEL_OCTANE'] in its workers only; the octane.enabled config defaults to true everywhere.
        $this->webUnderOctane = $webUnderOctane ?? static fn (): bool => !app()->runningInConsole() && ($_SERVER['LARAVEL_OCTANE'] ?? false) !== false;
    }

    /** @var Closure(): bool */
    private Closure $webUnderOctane;

    // Re-run on every pass, not only the first.
    private const array ALWAYS = [SetupStep::Preflight, SetupStep::MaintenanceDown, SetupStep::Innodb, SetupStep::CacheTables, SetupStep::MaintenanceUp];

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
            $this->forgetPendingVerdict();

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
            $this->forgetPendingVerdict();

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
     * Run only the next step that needs running, so a web request can drive a run one step at a time.
     * Steps that run on every pass (preflight, maintenance, innodb, cache tables) run once per pass, in order.
     *
     * @param (Closure(string): void)|null $echo
     */
    public function runNextStep(?Closure $echo = null): StepOutcome
    {
        if (!$this->lock->acquire(0)) {
            return StepOutcome::Busy;
        }

        try {
            return $this->nextStep($echo);
        } finally {
            $this->lock->release();
        }
    }

    /**
     * @param (Closure(string): void)|null $echo
     */
    private function nextStep(?Closure $echo): StepOutcome
    {
        $snapshot = $this->state->read();

        if (!$snapshot instanceof RunSnapshot || !in_array($snapshot->status, [RunStatus::Requested, RunStatus::Running], true)) {
            return StepOutcome::NothingRequested;
        }

        // The migrate step alone can outlast a web request's time limit.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // A new pass decides its always-run and skipped steps again; a Running run carries on where it stopped.
        if ($snapshot->status === RunStatus::Requested) {
            $this->state->beginPass(self::ALWAYS);
            $snapshot = $this->state->read() ?? $snapshot;
        }

        [$ctx, $line, $bound] = $this->prepare($snapshot, $echo);

        if ($snapshot->status === RunStatus::Requested) {
            $this->state->markRunning();
        }

        foreach (SetupStep::cases() as $case) {
            $status = $snapshot->steps[$case->value]['status'] ?? StepStatus::Pending;

            if (in_array($status, [StepStatus::Done, StepStatus::Skipped], true)) {
                continue;
            }

            // A web request cannot reload Octane; cron does it in the console.
            if ($case === SetupStep::OctaneReload && ($this->webUnderOctane)()) {
                return StepOutcome::WaitingForCron;
            }

            $outcome = $this->runStep($case, $bound[$case->value] ?? null, $ctx, $line);

            if ($outcome === false) {
                return StepOutcome::Failed;
            }

            // A skipped step costs nothing, so the call goes on to one that really runs.
            if ($outcome === true) {
                break;
            }
        }

        if ($this->hasUnhandledStep()) {
            return StepOutcome::StepRan;
        }

        return $this->complete($line) === RunOutcome::Ran ? StepOutcome::Finished : StepOutcome::Failed;
    }

    private function hasUnhandledStep(): bool
    {
        $snapshot = $this->state->read();

        return array_any(SetupStep::cases(), fn (SetupStep $case): bool => !in_array($snapshot?->steps[$case->value]['status'] ?? StepStatus::Pending, [StepStatus::Done, StepStatus::Skipped], true));
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

        [$ctx, $line, $bound] = $this->prepare($snapshot, $echo);

        $this->state->markRunning();

        foreach (SetupStep::cases() as $case) {
            // Re-checked every time: an operator may have brought the site up before a retry.
            // Innodb too: a database restored from the backup before a retry is MyISAM again.
            if (!in_array($case, self::ALWAYS, true) && ($snapshot->steps[$case->value]['status'] ?? StepStatus::Pending) === StepStatus::Done) {
                continue;
            }

            if ($this->runStep($case, $bound[$case->value] ?? null, $ctx, $line) === false) {
                return RunOutcome::Failed;
            }
        }

        return $this->complete($line);
    }

    /**
     * @param  (Closure(string): void)|null                                   $echo
     * @return array{StepContext, Closure(string): void, array<string, Step>}
     */
    private function prepare(RunSnapshot $snapshot, ?Closure $echo): array
    {
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

        return [$ctx, $line, $bound];
    }

    /**
     * True when the step ran, null when it was skipped, false when it failed (the run is then Failed).
     *
     * @param Closure(string): void $line
     */
    private function runStep(SetupStep $case, ?Step $step, StepContext $ctx, Closure $line): ?bool
    {
        try {
            // applies() probes the database, so a throw there fails the step too.
            if (!$step instanceof Step || !$step->applies($ctx)) {
                $this->state->stepSkipped($case);

                return null;
            }

            $this->state->stepStarted($case);
            $line('==> '.$case->value);
            $step->run($ctx);
        } catch (Throwable $throwable) {
            $error = $this->publicMessage($throwable);
            $line('Failed: '.$error);
            $this->state->stepFailed($case, $error);
            $this->finish(RunStatus::Failed);

            return false;
        }

        $this->state->stepDone($case);

        return true;
    }

    /**
     * @param Closure(string): void $line
     */
    private function complete(Closure $line): RunOutcome
    {
        // Written first: a Done run without the file would leave /system/upgrade open.
        try {
            $this->completed->write();
        } catch (Throwable $throwable) {
            $error = $this->publicMessage($throwable);
            $line('Failed: '.$error);
            $this->finish(RunStatus::Failed, $error);

            return RunOutcome::Failed;
        }

        $this->finish(RunStatus::Done);

        return RunOutcome::Ran;
    }

    private function finish(RunStatus $status, ?string $error = null): void
    {
        $this->state->finished($status, $error);
        $this->forgetPendingVerdict();
    }

    private function forgetPendingVerdict(): void
    {
        app(CachedPendingWork::class)->forget();
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
