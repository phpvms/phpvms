<?php

declare(strict_types=1);

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepOutcome;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/phpvms-next-step-'.uniqid();
    mkdir($this->dir);
    $this->state = new SetupRunState($this->dir.'/state.json');
    $this->lockPath = $this->dir.'/run.lock';
    $this->completed = new UpgradeCompleted($this->dir.'/completed', databaseSignal: false);
    $this->ran = new ArrayObject();
    $this->cache = new CachedPendingWork(app(DetectPendingWork::class), app(MigrationPaths::class), app(CoreSeeds::class), app(SetupRunState::class), $this->dir.'/pending.json');
    app()->instance(CachedPendingWork::class, $this->cache);
});

afterEach(function (): void {
    unset($_SERVER['LARAVEL_OCTANE']);
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

function nextStepFake(SetupStep $which, ArrayObject $ran, ?Closure $throws = null): Step
{
    return new readonly class($which, $ran, $throws) implements Step
    {
        public function __construct(private SetupStep $which, private ArrayObject $ran, private ?Closure $throws) {}

        public function step(): SetupStep
        {
            return $this->which;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            $this->ran->append($this->which);

            if ($this->throws instanceof Closure && ($this->throws)()) {
                throw new RuntimeException('boom');
            }
        }
    };
}

function nextStepEngine(object $test, array $steps, ?Closure $webUnderOctane = null): RunSetup
{
    return new RunSetup($test->state, new SetupLock($test->lockPath), $steps, $test->completed, $webUnderOctane);
}

function drainNextSteps(RunSetup $engine, int $max = 40): array
{
    $outcomes = [];

    do {
        $outcomes[] = $outcome = $engine->runNextStep();
    } while ($outcome === StepOutcome::StepRan && count($outcomes) < $max);

    return $outcomes;
}

test('runs one step per call and finishes the run', function (): void {
    $engine = nextStepEngine($this, [
        nextStepFake(SetupStep::Migrate, $this->ran),
        nextStepFake(SetupStep::Preflight, $this->ran),
        nextStepFake(SetupStep::Seed, $this->ran),
    ]);
    $this->state->request('installer', true);

    expect($engine->runNextStep())->toBe(StepOutcome::StepRan)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight])
        ->and($this->state->read()->status)->toBe(RunStatus::Running);

    $outcomes = drainNextSteps($engine);

    expect(end($outcomes))->toBe(StepOutcome::Finished)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate, SetupStep::Seed])
        ->and($this->state->read()->status)->toBe(RunStatus::Done)
        ->and($this->completed->exists())->toBeTrue()
        ->and($engine->runNextStep())->toBe(StepOutcome::NothingRequested);
});

test('each always-run step runs once per pass, in order', function (): void {
    $engine = nextStepEngine($this, [
        nextStepFake(SetupStep::MaintenanceUp, $this->ran),
        nextStepFake(SetupStep::Preflight, $this->ran),
        nextStepFake(SetupStep::Migrate, $this->ran),
        nextStepFake(SetupStep::MaintenanceDown, $this->ran),
    ]);
    $this->state->request('installer', true);

    $outcomes = drainNextSteps($engine);

    expect(end($outcomes))->toBe(StepOutcome::Finished)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::MaintenanceDown, SetupStep::Migrate, SetupStep::MaintenanceUp]);
});

test('a retry after a failed step keeps the done steps and reruns the always-run ones', function (): void {
    $broken = true;
    $engine = nextStepEngine($this, [
        nextStepFake(SetupStep::Preflight, $this->ran),
        nextStepFake(SetupStep::LinkAddons, $this->ran),
        nextStepFake(SetupStep::Migrate, $this->ran, function () use (&$broken): bool {
            return $broken;
        }),
    ]);
    $this->state->request('installer', true);

    $outcomes = drainNextSteps($engine);

    expect(end($outcomes))->toBe(StepOutcome::Failed)
        ->and($this->state->read()->status)->toBe(RunStatus::Failed)
        ->and($this->state->read()->steps['link_addons']['status'])->toBe(StepStatus::Done);

    $broken = false;
    $this->ran->exchangeArray([]);
    $this->state->request('installer', true);

    $outcomes = drainNextSteps($engine);

    expect(end($outcomes))->toBe(StepOutcome::Finished)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate])
        ->and($this->state->read()->status)->toBe(RunStatus::Done);
});

test('a stale running run with a free lock carries on from its first unfinished step', function (): void {
    $engine = nextStepEngine($this, [
        nextStepFake(SetupStep::Preflight, $this->ran),
        nextStepFake(SetupStep::Migrate, $this->ran),
    ]);
    $this->state->request('installer', true);
    $this->state->markRunning();
    $this->state->stepStarted(SetupStep::Preflight);
    $this->state->stepDone(SetupStep::Preflight);

    $outcomes = drainNextSteps($engine);

    expect(end($outcomes))->toBe(StepOutcome::Finished)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Migrate]);
});

test('reports busy while another process holds the lock', function (): void {
    $engine = nextStepEngine($this, [nextStepFake(SetupStep::Migrate, $this->ran)]);
    $this->state->request('installer', true);
    $other = new SetupLock($this->lockPath);
    $other->acquire();

    expect($engine->runNextStep())->toBe(StepOutcome::Busy)
        ->and($this->ran->getArrayCopy())->toBe([]);

    $other->release();

    expect($engine->runNextStep())->not->toBe(StepOutcome::Busy);
});

test('web under octane leaves octane_reload pending and a full run finishes it', function (): void {
    $engine = nextStepEngine($this, [
        nextStepFake(SetupStep::Migrate, $this->ran),
        nextStepFake(SetupStep::OctaneReload, $this->ran),
    ], fn (): bool => true);
    $this->state->request('installer', true);

    $outcomes = drainNextSteps($engine);
    $run = $this->state->read();

    expect(end($outcomes))->toBe(StepOutcome::WaitingForCron)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Migrate])
        ->and($run->status)->toBe(RunStatus::Running)
        ->and($run->steps['octane_reload']['status'])->toBe(StepStatus::Pending)
        ->and($this->completed->exists())->toBeFalse()
        ->and($engine->runNextStep())->toBe(StepOutcome::WaitingForCron);

    expect($engine->handle())->toBe(RunOutcome::Ran)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::Migrate, SetupStep::OctaneReload])
        ->and($this->state->read()->status)->toBe(RunStatus::Done);
});

test('octane is only skipped on the web: the console reloads it', function (): void {
    $_SERVER['LARAVEL_OCTANE'] = 1;
    // Tests run in the console, so the default detection must not wait.
    $engine = nextStepEngine($this, [nextStepFake(SetupStep::OctaneReload, $this->ran)]);
    $this->state->request('installer', true);

    $outcomes = drainNextSteps($engine);

    expect(end($outcomes))->toBe(StepOutcome::Finished)
        ->and($this->ran->getArrayCopy())->toBe([SetupStep::OctaneReload]);
});

test('the pending verdict cache is cleared when a run is requested and when it finishes', function (): void {
    $engine = nextStepEngine($this, [nextStepFake(SetupStep::Migrate, $this->ran)]);
    $cachePath = $this->dir.'/pending.json';

    $this->cache->handle();
    expect($cachePath)->toBeFile();

    $engine->request('installer', true);
    expect($cachePath)->not->toBeFile();

    $this->cache->handle();
    drainNextSteps($engine);

    expect($this->state->read()->status)->toBe(RunStatus::Done)
        ->and($cachePath)->not->toBeFile();
});

test('the pending verdict cache is cleared when a run fails', function (): void {
    $engine = nextStepEngine($this, [nextStepFake(SetupStep::Migrate, $this->ran, fn (): bool => true)]);
    $engine->request('installer', true);

    $this->cache->handle();

    drainNextSteps($engine);

    expect($this->state->read()->status)->toBe(RunStatus::Failed)
        ->and($this->dir.'/pending.json')->not->toBeFile();
});
