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
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
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

test('the web cron leaves a requested run to the CLI cron', function (): void {
    $ran = new ArrayObject();
    $steps = array_map(fn (SetupStep $s): Step => cronTaskStep($s, $ran), SetupStep::cases());
    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        app(UpgradeCompleted::class),
    ));
    app(SetupRunState::class)->request('cli', false);
    updateSetting('cron.random_id', 'web-cron-id');

    $this->get('/api/cron/web-cron-id')->assertOk();

    expect(app(SetupRunState::class)->read()->status)->toBe(RunStatus::Requested)
        ->and($ran->count())->toBe(0);

    Artisan::call('schedule:run');

    expect(app(SetupRunState::class)->read()->status)->toBe(RunStatus::Done);
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

test('every scheduled task waits out an update, add-on ones included', function (): void {
    // Production consoles raise CommandStarting; test runs leave it off.
    app(ConsoleKernel::class)->rerouteSymfonyCommandEvents();
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
    $ran = new ArrayObject();
    $steps = array_map(fn (SetupStep $s): Step => cronTaskStep($s, $ran), SetupStep::cases());
    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        app(UpgradeCompleted::class),
    ));
    $addonRuns = new ArrayObject();
    app(Schedule::class)->call(static fn (): null => $addonRuns->append(now()->format('H:i')))->name('addon-task')->everyMinute();
    EventFacade::fake([CronFiveMinute::class]);

    // The update runs in this tick; nothing else due in it fires, even after it ends.
    $this->travelTo(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
    app(SetupRunState::class)->request('cli', false);
    Artisan::call('schedule:run');

    expect(app(SetupRunState::class)->read()->status)->toBe(RunStatus::Done)
        ->and($addonRuns->getArrayCopy())->toBe([]);
    EventFacade::assertNotDispatched(CronFiveMinute::class);

    $this->travelTo(Carbon::parse('2026-11-01 00:05:00', 'UTC'));
    Artisan::call('schedule:run');

    expect($addonRuns->getArrayCopy())->toBe(['00:05']);
    EventFacade::assertDispatched(CronFiveMinute::class);
});

test('a failed update pauses scheduled tasks only while the site is down', function (): void {
    // Production consoles raise CommandStarting; test runs leave it off.
    app(ConsoleKernel::class)->rerouteSymfonyCommandEvents();
    config(['app.maintenance.driver' => 'file']);
    $addonRuns = new ArrayObject();
    app(Schedule::class)->call(static fn (): null => $addonRuns->append(true))->name('addon-task')->everyMinute()->evenInMaintenanceMode();
    $state = app(SetupRunState::class);
    $state->request('cli', false);
    $state->markRunning();
    $state->finished(RunStatus::Failed, 'boom');
    Artisan::call('down');

    Artisan::call('schedule:run');
    expect($addonRuns->count())->toBe(0);

    // The admin fixed the site by hand and brought it up.
    Artisan::call('up');
    Artisan::call('schedule:run');
    expect($addonRuns->count())->toBe(1);
});

test('the admin warns when scheduled tasks stay paused, and stops once the state file is removed', function (): void {
    // Production consoles raise CommandStarting; test runs leave it off.
    app(ConsoleKernel::class)->rerouteSymfonyCommandEvents();
    $state = app(SetupRunState::class);
    $state->request('cli', false);
    $state->markRunning();
    // setup-run skips, so the run stays unfinished.
    Context::addHidden(RunSetup::WEB_CRON, true);
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    $warning = __('installer.updater.schedule_paused', ['since' => '', 'file' => '']);
    $warning = substr($warning, 0, (int) strpos($warning, ' because'));

    $this->travelTo(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
    Artisan::call('schedule:run');
    $this->get('/admin')->assertDontSee($warning);

    $this->travelTo(Carbon::parse('2026-11-01 01:00:00', 'UTC'));
    Artisan::call('schedule:run');
    $this->get('/admin')->assertSee($warning);

    $state->clear();
    Artisan::call('schedule:run');
    $this->get('/admin')->assertDontSee($warning);
    Context::forgetHidden(RunSetup::WEB_CRON);
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
