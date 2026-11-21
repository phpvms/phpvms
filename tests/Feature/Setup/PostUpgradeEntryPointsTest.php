<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\PostUpgrade\BackfillPirepEvents;
use App\Features\Setup\Steps\RunDataTasks;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;

/**
 * Every step is a no-op except data_tasks, which is the real one.
 */
function entryPointSteps(): void
{
    $steps = array_map(fn (SetupStep $which): Step => $which === SetupStep::DataTasks ? app(RunDataTasks::class) : new readonly class($which) implements Step
    {
        public function __construct(private SetupStep $which) {}

        public function step(): SetupStep
        {
            return $this->which;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void {}
    }, SetupStep::cases());

    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(app(SetupRunState::class), app(SetupLock::class), $steps, app(UpgradeCompleted::class)));
}

beforeEach(function (): void {
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

    entryPointSteps();
});

test('phpvms:update copies the old flight logs as part of the upgrade run', function (): void {
    $pirep = Pirep::factory()->create();
    Acars::factory()->count(3)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);
    app(SetupRunState::class)->request('cli', false);

    $this->artisan('phpvms:update')->assertExitCode(0);

    $run = app(SetupRunState::class)->read();

    expect($run->status)->toBe(RunStatus::Done)
        ->and($run->steps[SetupStep::DataTasks->value]['status'])->toBe(StepStatus::Done)
        ->and(PirepEvent::where('pirep_id', $pirep->id)->count())->toBe(3)
        ->and(app(PostUpgradeTaskState::class)->status(BackfillPirepEvents::KEY))->toBe(PostUpgradeStatus::Done);
});

test('phpvms:update with nothing pending exits 0 without starting a run', function (): void {
    $this->artisan('phpvms:update')->assertExitCode(0);

    expect(app(SetupRunState::class)->read())->toBeNull();
});
