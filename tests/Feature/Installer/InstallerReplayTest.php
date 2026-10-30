<?php

declare(strict_types=1);

use App\Filament\System\Installer;
use App\Models\User;
use App\Services\Installer\MigrationService;
use App\Services\Installer\StreamedCommandsService;
use Livewire\Livewire;

/**
 * The installer page is public until the first user exists. A visitor who
 * loaded it before the operator finished holds a validly signed Livewire
 * snapshot; replaying it against /livewire/update after the install must be
 * refused on every request, not only on the page GET. Filament re-runs
 * canAccess() from hydrateCanAuthorizeAccess(), which is what these pin down.
 */
beforeEach(function (): void {
    // Neither method may ever reach the shell: a regression here would run
    // `migrate` and `db:seed` against the development database.
    $this->partialMock(MigrationService::class, function ($mock): void {
        $mock->shouldNotReceive('runAllMigrationsWithStreaming');
    });
    $this->partialMock(StreamedCommandsService::class, function ($mock): void {
        $mock->shouldNotReceive('streamArtisanCommand');
    });
});

it('refuses a replayed save once the first user exists', function (): void {
    // Two pre-install snapshots: a 403 response carries no snapshot, so each
    // replayed request needs its own.
    $filled = Livewire::test(Installer::class);
    $bare = Livewire::test(Installer::class);

    User::factory()->create();

    $filled
        ->set('user', [
            'airline_icao'          => 'EVL',
            'airline_name'          => 'Evil Air',
            'airline_country'       => 'US',
            'name'                  => 'Intruder',
            'email'                 => 'intruder@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertForbidden();

    $bare->call('save')->assertForbidden();

    expect(User::query()->where('email', 'intruder@example.com')->exists())->toBeFalse();
});

it('refuses a replayed migration run once the first user exists', function (): void {
    $component = Livewire::test(Installer::class);

    User::factory()->create();

    $component->call('runMigrations')->assertForbidden();
});
