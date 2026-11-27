<?php

declare(strict_types=1);

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Pages\SystemUpdates;
use App\Services\CronService;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function systemUpdatesPage(Testable $component): SystemUpdates
{
    $page = $component->instance();
    assert($page instanceof SystemUpdates);

    return $page;
}

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
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
    app(UpgradeCompleted::class)->write();
    app(CronService::class)->updateLastRunTime();

    $this->admin = createAdminUser();
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
});

test('start queues the run and stays on the page', function (): void {
    $component = Livewire::test(SystemUpdates::class)
        ->set('confirmed', true)
        ->call('start')
        ->assertNoRedirect();

    $run = app(SetupRunState::class)->read();

    expect($run?->status)->toBe(RunStatus::Requested)
        ->and($run?->requestedBy)->toBe((string) $this->admin->id)
        ->and($run?->fresh)->toBeFalse()
        ->and(systemUpdatesPage($component)->screen())->toBe('progress');
});

test('start needs the backup confirmation and a fresh cron heartbeat', function (): void {
    Livewire::test(SystemUpdates::class)->call('start');
    expect(app(SetupRunState::class)->read())->toBeNull();

    $this->travel(10)->minutes();

    Livewire::test(SystemUpdates::class)->set('confirmed', true)->call('start');
    expect(app(SetupRunState::class)->read())->toBeNull();
});

test('the page follows the run to the done screen', function (): void {
    $component = Livewire::test(SystemUpdates::class)->set('confirmed', true)->call('start');
    $state = app(SetupRunState::class);

    $state->markRunning();

    $component->call('$refresh');

    expect(systemUpdatesPage($component)->screen())->toBe('progress');

    $state->finished(RunStatus::Done);
    $component->call('$refresh');

    expect(systemUpdatesPage($component)->screen())->toBe('done');
});

test('a failed run shows its step and retry queues it again', function (): void {
    $state = app(SetupRunState::class);
    $state->request((string) $this->admin->id, false);
    $state->markRunning();
    $state->stepDone(SetupStep::Preflight);
    $state->stepFailed(SetupStep::Migrate, 'disk full');
    $state->finished(RunStatus::Failed);

    $component = Livewire::test(SystemUpdates::class);

    expect(systemUpdatesPage($component)->screen())->toBe('failed')
        ->and(systemUpdatesPage($component)->failedStep())->toBe(SetupStep::Migrate->label());

    $component->call('retry');

    expect($state->read()?->status)->toBe(RunStatus::Requested)
        ->and($state->read()?->steps['preflight']['status']->value)->toBe('done')
        ->and(systemUpdatesPage($component)->screen())->toBe('progress');
});

test('an old done run does not hide the start form', function (): void {
    $state = app(SetupRunState::class);
    $state->request('web', false);
    $state->markRunning();
    $state->finished(RunStatus::Done);

    expect(systemUpdatesPage(Livewire::test(SystemUpdates::class))->screen())->toBe('idle');
});
