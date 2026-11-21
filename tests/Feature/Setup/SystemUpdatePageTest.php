<?php

declare(strict_types=1);

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\PostUpgrade\ScanThemeForDeprecations;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\RunLog;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\System\Updater;
use App\Services\CronService;
use Igaster\LaravelTheme\Theme as ThemeDefinition;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function pendingSchema(array $pending): void
{
    app()->instance(MigrationPaths::class, new class($pending) extends MigrationPaths
    {
        public function __construct(private readonly array $pending) {}

        public function pendingSchema(): array
        {
            return $this->pending;
        }

        public function pendingData(): array
        {
            return [];
        }
    });
}

function updaterPage(Testable $component): Updater
{
    $page = $component->instance();
    assert($page instanceof Updater);

    return $page;
}

function failRun(): void
{
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->markRunning();
    $state->stepDone(SetupStep::Preflight);
    $state->stepFailed(SetupStep::MaintenanceDown, 'disk full');
    $state->finished(RunStatus::Failed);
}

beforeEach(function (): void {
    pendingSchema(['2026_01_01_000000_pending']);
});

test('returns 404 once the completion file exists', function (): void {
    app(CronService::class)->updateLastRunTime();
    $component = Livewire::test(Updater::class)->set('confirmed', true);

    app(UpgradeCompleted::class)->write();

    $this->get('/system/upgrade')->assertNotFound();

    $component->call('start')->assertStatus(404);

    expect(app(SetupRunState::class)->read())->toBeNull();
});

test('start writes a requested run', function (): void {
    app(CronService::class)->updateLastRunTime();

    Livewire::test(Updater::class)
        ->set('confirmed', true)
        ->call('start');

    $run = app(SetupRunState::class)->read();

    expect($run?->status)->toBe(RunStatus::Requested)
        ->and($run?->requestedBy)->toBe('web')
        ->and($run?->fresh)->toBeFalse();
});

test('a started run says it is waiting for cron until cron picks it up', function (): void {
    app(CronService::class)->updateLastRunTime();

    $component = Livewire::test(Updater::class)
        ->set('confirmed', true)
        ->call('start')
        ->assertSee(__('installer.updater.waiting_for_cron'));

    expect(app(SetupRunState::class)->read()?->logTail)->toBe(['==> '.__('installer.updater.waiting_for_cron')]);

    app(SetupRunState::class)->markRunning();
    unset($component->instance()->run);

    $component->call('$refresh')->assertDontSee(__('installer.updater.waiting_for_cron').'</p>', false);
});

test('the log shows the whole run, not only the lines the state file keeps', function (): void {
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->markRunning();

    $log = RunLog::for($state->read()->id);

    foreach (range(1, 250) as $i) {
        $line = sprintf('line %03d', $i);
        $log->write($line);
        $state->appendLog($line);
    }

    Livewire::test(Updater::class)
        ->assertSee('line 001')
        ->assertSee('line 250');
});

test('no Start while the cron heartbeat is stale', function (): void {
    $component = Livewire::test(Updater::class)
        ->assertSee(app(CronService::class)->getCronExecString())
        ->assertDontSee(__('installer.updater.start'));

    expect(updaterPage($component)->canStart())->toBeFalse();
});

test('retry requests the run again after a failure', function (): void {
    failRun();

    $component = Livewire::test(Updater::class);

    expect(updaterPage($component)->screen())->toBe('failed');

    $component->call('retry');

    expect(app(SetupRunState::class)->read()?->status)->toBe(RunStatus::Requested)
        ->and(updaterPage($component)->screen())->toBe('progress');
});

test('start is refused on the server without confirmation or with a stale cron', function (): void {
    $component = Livewire::test(Updater::class);

    $component->set('confirmed', true)->call('start');
    expect(app(SetupRunState::class)->read())->toBeNull();

    app(CronService::class)->updateLastRunTime();

    $component->set('confirmed', false)->call('start');
    expect(app(SetupRunState::class)->read())->toBeNull();
});

test('a fresh visit after the completion file appears and the run is done returns 404', function (): void {
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->markRunning();
    $state->finished(RunStatus::Done);

    app(UpgradeCompleted::class)->write();

    $this->get('/system/upgrade')->assertNotFound();
    Livewire::test(Updater::class)->assertStatus(404);
});

test('a page that watched the run shows the done screen after it finishes', function (): void {
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->markRunning();

    $component = Livewire::test(Updater::class);

    $state->finished(RunStatus::Done);
    app(UpgradeCompleted::class)->write();

    $component->call('$refresh')
        ->assertOk()
        ->assertSee(updaterPage($component)->adminUrl());

    expect(updaterPage($component)->screen())->toBe('done');
});

test('the completed screen warns when the post-upgrade scan found deprecated theme features', function (): void {
    new ThemeDefinition('legacy-7x', null, '/tests/fixtures/themes/legacy-7x');
    updateSetting('general.theme', 'legacy-7x');
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->markRunning();

    $component = Livewire::test(Updater::class);

    app(ScanThemeForDeprecations::class)->run();
    $state->finished(RunStatus::Done);
    app(UpgradeCompleted::class)->write();

    $component->call('$refresh')
        ->assertSee(__('installer.updater.theme_deprecated', ['theme' => 'legacy-7x', 'items' => '$moduleSvc, @ability']), false);

    expect(app(ScanThemeForDeprecations::class)->pending())->toBeFalse();
});

test('after the completion file exists, a failed run still shows progress and Retry but no Start', function (): void {
    failRun();
    app(UpgradeCompleted::class)->write();
    app(CronService::class)->updateLastRunTime();

    $this->get('/system/upgrade')->assertOk();

    $component = Livewire::test(Updater::class);

    expect(updaterPage($component)->screen())->toBe('failed')
        ->and(updaterPage($component)->canStart())->toBeFalse();

    $component->call('retry');

    expect(app(SetupRunState::class)->read()?->status)->toBe(RunStatus::Requested);
});
