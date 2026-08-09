<?php

declare(strict_types=1);

use App\Filament\System\Installer;
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

// Deliberately not covered here: calling runMigrations(). It shells out to
// `php artisan migrate` through StreamedCommandsService, and the subprocess
// reads .env rather than phpunit's forced sqlite connection -- so the assertion
// would run migrations and DatabaseSeeder against the development database.
