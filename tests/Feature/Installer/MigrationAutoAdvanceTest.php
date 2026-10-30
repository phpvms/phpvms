<?php

declare(strict_types=1);

use App\Filament\System\Installer;
use App\Services\Installer\MigrationService;
use Livewire\Livewire;

/**
 * The countdown hangs off the wizard footer that Filament renders for us, so
 * these assertions are really about the seam with the vendor component: the
 * Next button has to stay findable from JS, and the Back slot has to have been
 * taken over by Pause.
 */
it('renders the auto-advance countdown hooks on the wizard footer', function (): void {
    Livewire::test(Installer::class)
        ->assertSee('data-installer-next', escape: false)
        ->assertSee("Alpine.store('installerAutoAdvance'", escape: false)
        ->assertSee('$store.installerAutoAdvance.toggle()', escape: false);
});

it('replaces the wizard back button with the pause control', function (): void {
    Livewire::test(Installer::class)
        ->assertSee('Pause')
        ->assertDontSee(__('filament-schemas::components.wizard.actions.previous_step.label'));
});

it('restarts the wizard when the snapshot says done but the database has pending migrations', function (): void {
    $this->partialMock(MigrationService::class, function ($mock): void {
        $mock->shouldReceive('migrationsAvailable')->andReturn(['2099_01_01_000000_pending']);
    });

    // A tab that ran the migrations before the database was dropped carries
    // this flag in its snapshot; the next request must trust the database and
    // send the operator back to a fresh mount, not patch the stale wizard.
    Livewire::test(Installer::class)
        ->set('autoMigrationStarted', true)
        ->call('$refresh')
        ->assertRedirect(Installer::getUrl(panel: 'system'))
        ->assertNotified(__('installer.restarted_database_changed'));
});

it('leaves a finished migration run alone when nothing is pending', function (): void {
    $this->partialMock(MigrationService::class, function ($mock): void {
        $mock->shouldReceive('migrationsAvailable')->andReturn([]);
    });

    Livewire::test(Installer::class)
        ->set('autoMigrationStarted', true)
        ->call('$refresh')
        ->assertNoRedirect()
        ->assertSet('autoMigrationStarted', true)
        ->assertDontSeeHtml('wire:init="runMigrations"');
});

// Deliberately not covered here: calling runMigrations(). It shells out to
// `php artisan migrate` through StreamedCommandsService, and the subprocess
// reads .env rather than phpunit's forced sqlite connection -- so the assertion
// would run migrations and DatabaseSeeder against the development database.

it('does not persist the wizard step in the query string', function (): void {
    // A stale `?step=form.migrations::wizard-step` would otherwise pin a fresh
    // load to the migration step with nothing pending and nothing to run.
    Livewire::test(Installer::class)
        ->assertSeeHtml('isStepPersistedInQueryString: false');
});
