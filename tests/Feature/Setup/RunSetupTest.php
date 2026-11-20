<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/phpvms-run-setup-'.uniqid();
    mkdir($this->dir);
    $this->state = new SetupRunState($this->dir.'/state.json');
    $this->lockPath = $this->dir.'/run.lock';
    $this->completed = new UpgradeCompleted($this->dir.'/completed', databaseSignal: false);
    $this->ran = new ArrayObject();
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

function fakeSetupStep(SetupStep $which, ArrayObject $ran, bool $throws = false, bool $applies = true, bool $appliesThrows = false): Step
{
    return new readonly class($which, $ran, $throws, $applies, $appliesThrows) implements Step
    {
        public function __construct(private SetupStep $which, private ArrayObject $ran, private bool $throws, private bool $applies, private bool $appliesThrows) {}

        public function step(): SetupStep
        {
            return $this->which;
        }

        public function applies(StepContext $ctx): bool
        {
            if ($this->appliesThrows) {
                throw new RuntimeException('probe failed');
            }

            return $this->applies;
        }

        public function run(StepContext $ctx): void
        {
            $this->ran->append($this->which);
            ($ctx->log)('ran '.$this->which->value);

            if ($this->throws) {
                throw new RuntimeException('boom');
            }
        }
    };
}

function runSetupEngine(object $test, array $steps): RunSetup
{
    return new RunSetup($test->state, new SetupLock($test->lockPath), $steps, $test->completed);
}

test('runs steps in SetupStep order and finishes done', function (): void {
    $steps = [
        fakeSetupStep(SetupStep::Migrate, $this->ran),
        fakeSetupStep(SetupStep::Preflight, $this->ran),
        fakeSetupStep(SetupStep::Seed, $this->ran, applies: false),
    ];
    $this->state->request('cli', false);
    $lines = [];

    $outcome = runSetupEngine($this, $steps)->handle(function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    $run = $this->state->read();

    expect($outcome)->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate])
        ->and($run->status)->toBe(RunStatus::Done)
        ->and($run->steps['preflight']['status'])->toBe(StepStatus::Done)
        ->and($run->steps['seed']['status'])->toBe(StepStatus::Skipped)
        ->and($run->steps['octane_reload']['status'])->toBe(StepStatus::Skipped)
        ->and($run->logTail)->toContain('ran migrate')
        ->and($lines)->toContain('ran migrate')
        ->and($this->completed->exists())->toBeTrue();
});

test('a throwing step stops the run, marks it failed, and later steps stay pending', function (): void {
    $steps = [
        fakeSetupStep(SetupStep::Preflight, $this->ran),
        fakeSetupStep(SetupStep::Migrate, $this->ran, throws: true),
        fakeSetupStep(SetupStep::Seed, $this->ran),
    ];
    $this->state->request('cli', false);

    $outcome = runSetupEngine($this, $steps)->handle();

    $run = $this->state->read();

    expect($outcome)->toBe(RunOutcome::Failed)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate])
        ->and($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('boom')
        ->and($run->steps['migrate']['status'])->toBe(StepStatus::Failed)
        ->and($run->steps['seed']['status'])->toBe(StepStatus::Pending)
        ->and($this->completed->exists())->toBeFalse();
});

test('a step whose applies check throws fails the run and later steps stay pending', function (): void {
    $steps = [
        fakeSetupStep(SetupStep::Preflight, $this->ran),
        fakeSetupStep(SetupStep::Migrate, $this->ran, appliesThrows: true),
        fakeSetupStep(SetupStep::Seed, $this->ran),
    ];
    $this->state->request('cli', false);

    $outcome = runSetupEngine($this, $steps)->handle();

    $run = $this->state->read();

    expect($outcome)->toBe(RunOutcome::Failed)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight])
        ->and($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('probe failed')
        ->and($run->steps['migrate']['status'])->toBe(StepStatus::Failed)
        ->and($run->steps['seed']['status'])->toBe(StepStatus::Pending);
});

test('resumes a running run at the first step not done', function (): void {
    $this->state->request('cli', false);
    $this->state->markRunning();

    foreach ([SetupStep::Preflight, SetupStep::MaintenanceDown, SetupStep::Innodb] as $done) {
        $this->state->stepDone($done);
    }

    $this->state->stepStarted(SetupStep::CacheTables);

    $steps = array_map(fn (SetupStep $s): Step => fakeSetupStep($s, $this->ran), SetupStep::cases());

    $outcome = runSetupEngine($this, $steps)->handle();

    expect($outcome)->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::MaintenanceDown, ...array_slice(SetupStep::cases(), 3)]);
});

test('returns Busy and runs nothing while another process holds the lock', function (): void {
    $this->state->request('cli', false);
    $other = new SetupLock($this->lockPath);
    expect($other->acquire())->toBeTrue();

    $outcome = runSetupEngine($this, [fakeSetupStep(SetupStep::Preflight, $this->ran)])->handle();

    $other->release();

    expect($outcome)->toBe(RunOutcome::Busy)
        ->and($this->ran->getArrayCopy())->toBe([])
        ->and($this->state->read()->status)->toBe(RunStatus::Requested);
});

test('returns NothingRequested when the state is done or absent', function (): void {
    $steps = [fakeSetupStep(SetupStep::Preflight, $this->ran)];

    expect(runSetupEngine($this, $steps)->handle())->toBe(RunOutcome::NothingRequested);

    $this->state->request('cli', false);
    runSetupEngine($this, $steps)->handle();
    $this->ran->exchangeArray([]);

    expect(runSetupEngine($this, $steps)->handle())->toBe(RunOutcome::NothingRequested)
        ->and($this->ran->getArrayCopy())->toBe([]);
});

test('retry after failure re-runs the failed step and not the done ones', function (): void {
    $this->state->request('cli', false);
    runSetupEngine($this, [
        fakeSetupStep(SetupStep::Preflight, $this->ran),
        fakeSetupStep(SetupStep::Migrate, $this->ran, throws: true),
    ])->handle();
    $this->ran->exchangeArray([]);

    $engine = runSetupEngine($this, [
        fakeSetupStep(SetupStep::Preflight, $this->ran),
        fakeSetupStep(SetupStep::Migrate, $this->ran),
    ]);

    expect($engine->request('admin', false))->toBeTrue();

    $outcome = $engine->handle();

    expect($outcome)->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate])
        ->and($this->state->read()->status)->toBe(RunStatus::Done);
});

test('request returns false while the lock is held', function (): void {
    $other = new SetupLock($this->lockPath);
    $other->acquire();

    $requested = runSetupEngine($this, [])->request('admin', false);

    $other->release();

    expect($requested)->toBeFalse()
        ->and($this->state->read())->toBeNull();
});

test('requestAndRun records and runs the run in one call', function (): void {
    $outcome = runSetupEngine($this, [fakeSetupStep(SetupStep::Preflight, $this->ran)])->requestAndRun('cli', true);

    $run = $this->state->read();

    expect($outcome)->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight])
        ->and($run->requestedBy)->toBe('cli')
        ->and($run->fresh)->toBeTrue()
        ->and($run->status)->toBe(RunStatus::Done);
});

test('requestAndRun returns Busy and records nothing while the lock is held', function (): void {
    $other = new SetupLock($this->lockPath);
    $other->acquire();

    $outcome = runSetupEngine($this, [fakeSetupStep(SetupStep::Preflight, $this->ran)])->requestAndRun('cli', false);

    $other->release();

    expect($outcome)->toBe(RunOutcome::Busy)
        ->and($this->ran->getArrayCopy())->toBe([])
        ->and($this->state->read())->toBeNull();
});

test('a completion file that cannot be written fails the run instead of finishing it', function (): void {
    touch($this->dir.'/blocker');
    $this->completed = new UpgradeCompleted($this->dir.'/blocker/completed', databaseSignal: false);
    $this->state->request('cli', false);

    $outcome = runSetupEngine($this, [fakeSetupStep(SetupStep::Preflight, $this->ran)])->handle();

    $run = $this->state->read();

    expect($outcome)->toBe(RunOutcome::Failed)
        ->and($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->not->toBeNull()
        ->and($this->completed->exists())->toBeFalse();
});

test('preflight and maintenance_down re-run on resume even when recorded done', function (): void {
    $this->state->request('cli', false);
    $this->state->markRunning();

    foreach ([SetupStep::Preflight, SetupStep::MaintenanceDown, SetupStep::Innodb] as $done) {
        $this->state->stepDone($done);
    }

    $steps = array_map(fn (SetupStep $s): Step => fakeSetupStep($s, $this->ran), [SetupStep::Preflight, SetupStep::MaintenanceDown, SetupStep::Innodb]);

    $outcome = runSetupEngine($this, $steps)->handle();

    expect($outcome)->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::MaintenanceDown]);
});

test('a failed query stores the database error without the SQL or its bindings', function (): void {
    $step = new class() implements Step
    {
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
            throw new QueryException('mysql', 'update settings set value = ?', ['https://discord.test/secret-hook'], new RuntimeException('Data too long'));
        }
    };
    $this->state->request('cli', false);

    $outcome = runSetupEngine($this, [$step])->handle();

    $run = $this->state->read();

    expect($outcome)->toBe(RunOutcome::Failed)
        ->and($run->error)->toBe('Data too long')
        ->and(implode("\n", $run->logTail))->not->toContain('SQL:')
        ->and(implode("\n", $run->logTail))->not->toContain('secret-hook');
});

test('maintenance_up re-runs on resume even when recorded done', function (): void {
    $this->state->request('cli', false);
    $this->state->markRunning();
    $this->state->stepDone(SetupStep::MaintenanceUp);

    $outcome = runSetupEngine($this, [fakeSetupStep(SetupStep::MaintenanceUp, $this->ran)])->handle();

    expect($outcome)->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::MaintenanceUp]);
});
