<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;

function updateCommandStep(SetupStep $which, ArrayObject $ran): Step
{
    return new readonly class($which, $ran) implements Step
    {
        public function __construct(private SetupStep $which, private ArrayObject $ran) {}

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
        }
    };
}

function bindUpdateCommandEngine(ArrayObject $ran): void
{
    $steps = array_map(fn (SetupStep $s): Step => updateCommandStep($s, $ran), SetupStep::cases());
    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        app(UpgradeCompleted::class),
    ));
}

beforeEach(function (): void {
    // Nothing is pending unless a run is unfinished, so only the state file drives these tests.
    app()->instance(MigrationPaths::class, new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            return [];
        }

        public function pendingData(): array
        {
            return [];
        }
    });
});

test('--check fails while a run is unfinished', function (): void {
    app(SetupRunState::class)->request('cli', false);

    $this->artisan('phpvms:update', ['--check' => true])->assertExitCode(1);

    app(SetupRunState::class)->clear();

    $this->artisan('phpvms:update', ['--check' => true])->assertExitCode(0);
});

test('resuming a crashed run keeps its done steps and its fresh flag', function (): void {
    $ran = new ArrayObject();
    bindUpdateCommandEngine($ran);
    $state = app(SetupRunState::class);
    $state->request('cli', true);
    $state->markRunning();
    $state->stepDone(SetupStep::Preflight);

    $this->artisan('phpvms:update')->assertExitCode(0);

    $run = $state->read();

    expect($ran->getArrayCopy())->toBe(SetupStep::cases())
        ->and($run->status)->toBe(RunStatus::Done)
        ->and($run->fresh)->toBeTrue();
});

test('runs the engine and prints each step', function (): void {
    $ran = new ArrayObject();
    bindUpdateCommandEngine($ran);
    app(SetupRunState::class)->request('cli', false);

    $this->artisan('phpvms:update')
        ->expectsOutputToContain('==> '.SetupStep::Preflight->value)
        ->expectsOutputToContain('==> '.SetupStep::Migrate->value)
        ->assertExitCode(0);

    expect($ran->count())->toBe(count(SetupStep::cases()));
});

test('exits 1 when another process holds the lock past --wait', function (): void {
    $ran = new ArrayObject();
    bindUpdateCommandEngine($ran);
    app(SetupRunState::class)->request('cli', false);
    $lock = app(SetupLock::class);
    $lock->acquire();

    try {
        $this->artisan('phpvms:update', ['--wait' => 1])->assertExitCode(1);
    } finally {
        $lock->release();
    }

    expect($ran)->toHaveCount(0);
});

test('a caller waiting on the lock does not redo work the lock holder finished', function (): void {
    $ran = new ArrayObject();
    bindUpdateCommandEngine($ran);
    $lock = app(SetupLock::class);
    $lock->acquire();
    // Pending on the first check; by the next, the other process has finished and let go.
    app()->instance(MigrationPaths::class, new class($lock) extends MigrationPaths
    {
        private int $calls = 0;

        public function __construct(private readonly SetupLock $lock) {}

        public function pendingSchema(): array
        {
            if (++$this->calls === 1) {
                return ['2026_01_01_000000_pending'];
            }

            $this->lock->release();

            return [];
        }

        public function pendingData(): array
        {
            return [];
        }
    });

    $this->artisan('phpvms:update', ['--wait' => 5])->assertExitCode(0);

    expect($ran)->toHaveCount(0)
        ->and(app(SetupRunState::class)->read())->toBeNull();
});

test('records and runs a new run when work is pending', function (): void {
    $ran = new ArrayObject();
    bindUpdateCommandEngine($ran);
    app()->instance(MigrationPaths::class, new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            return ['2026_01_01_000000_pending'];
        }

        public function pendingData(): array
        {
            return [];
        }
    });

    $this->artisan('phpvms:update')->assertExitCode(0);

    $run = app(SetupRunState::class)->read();

    expect($run->requestedBy)->toBe('cli')
        ->and($run->status)->toBe(RunStatus::Done)
        ->and($ran->count())->toBe(count(SetupStep::cases()));
});
