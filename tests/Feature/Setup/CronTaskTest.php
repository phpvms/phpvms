<?php

declare(strict_types=1);

use App\Events\CronFifteenMinute;
use App\Events\CronFiveMinute;
use App\Events\CronHourly;
use App\Events\CronMonthly;
use App\Events\CronThirtyMinute;
use App\Events\CronWeekly;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\CronHealth;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Services\CronService;
use App\Services\KvpService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;

function cronTaskStep(SetupStep $which, ArrayObject $ran): Step
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

function scheduledEvent(string $name): ?Event
{
    foreach (app(Schedule::class)->events() as $event) {
        if ($event->description === $name) {
            return $event;
        }
    }

    return null;
}

test('setup-run and the heartbeat are registered to run in maintenance mode', function (): void {
    expect(scheduledEvent('setup-run')?->evenInMaintenanceMode)->toBeTrue()
        ->and(scheduledEvent('update-last-run-time')?->evenInMaintenanceMode)->toBeTrue();
});

test('cron is not fresh without a cron_last_run row, and fresh after the heartbeat runs', function (): void {
    expect(app(CronHealth::class)->isFresh())->toBeFalse();

    app(KvpService::class)->save('cron_last_run', '');

    expect(app(CronHealth::class)->isFresh())->toBeFalse();

    app(CronService::class)->updateLastRunTime();

    expect(app(CronHealth::class)->isFresh())->toBeTrue();

    $this->travel(10)->minutes();

    expect(app(CronHealth::class)->isFresh())->toBeFalse();
});

test('a requested run is picked up by schedule:run', function (): void {
    $ran = new ArrayObject();
    $steps = array_map(fn (SetupStep $s): Step => cronTaskStep($s, $ran), SetupStep::cases());
    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        app(UpgradeCompleted::class),
    ));
    app(SetupRunState::class)->request('cli', false);

    Artisan::call('schedule:run');

    expect(app(SetupRunState::class)->read()->status)->toBe(RunStatus::Done)
        ->and($ran->count())->toBe(count(SetupStep::cases()));
});

test('a run killed mid-step resumes on the next schedule:run', function (): void {
    $ran = new ArrayObject();
    $steps = array_map(fn (SetupStep $s): Step => cronTaskStep($s, $ran), SetupStep::cases());
    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        app(UpgradeCompleted::class),
    ));
    $state = app(SetupRunState::class);
    $state->request('cli', false);
    $state->markRunning();
    $state->stepDone(SetupStep::Preflight);
    $state->stepStarted(SetupStep::MaintenanceDown);

    Artisan::call('schedule:run');

    expect($ran->getArrayCopy())->toBe(SetupStep::cases())
        ->and($state->read()->status)->toBe(RunStatus::Done);
});

test('cron-* events do not run while the panel is blocked', function (): void {
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
    app(SetupRunState::class)->request('cli', false);

    foreach (['cron-five-minutes', 'cron-fifteen-minutes', 'cron-thirty-minutes', 'cron-hourly', 'cron-nightly', 'cron-weekly', 'cron-monthly'] as $name) {
        expect(scheduledEvent($name)->filtersPass(app()))->toBeFalse();
    }

    app(SetupRunState::class)->clear();

    expect(scheduledEvent('cron-five-minutes')->filtersPass(app()))->toBeTrue();
});

test('a failing pending-work check skips cron-* events but the heartbeat still runs', function (): void {
    app()->instance(MigrationPaths::class, new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            throw new RuntimeException('database unreachable');
        }
    });
    EventFacade::fake([CronFiveMinute::class, CronFifteenMinute::class, CronThirtyMinute::class, CronHourly::class, CronWeekly::class, CronMonthly::class]);
    // Midnight on a Sunday the 1st: every cron-* event except nightly is due.
    $this->travelTo(Carbon::parse('2026-11-01 00:00:00', 'UTC'));

    Artisan::call('schedule:run');

    EventFacade::assertNotDispatched(CronFiveMinute::class);
    EventFacade::assertNotDispatched(CronMonthly::class);
    expect(app(KvpService::class)->get('cron_last_run'))->not->toBeEmpty();
});
