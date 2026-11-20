<?php

declare(strict_types=1);

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\System\Updater;
use App\Services\CronService;
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
