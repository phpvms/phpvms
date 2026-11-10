<?php

declare(strict_types=1);

use App\Addons\Support\AddonCacheRefresher;
use App\Filament\System\Installer;
use App\Models\User;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\StreamedCommandsService;
use Livewire\Livewire;
use Mockery\MockInterface;

/**
 * Seed data can create users (the demo environment ships an admin). The
 * installer closes itself as soon as a user exists, so the migration step must
 * notice and hand over to the login page instead of advancing to a user/airline
 * step whose submit would be refused.
 *
 * Every service that shells out is mocked: the subprocess would otherwise run
 * `migrate` and `db:seed` against the development database.
 */
function mockInstallRun(bool $seedCreatesUser): void
{
    test()->mock(InstallerService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isUpgradePending')->andReturnTrue();
        $mock->shouldReceive('ensurePassportKeys');
        $mock->shouldReceive('linkStorage')->andReturnTrue();
        $mock->shouldReceive('linkBundledAddons')->andReturnTrue();
    });

    test()->mock(MigrationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('migrationsAvailable')->andReturn([]);
        $mock->shouldReceive('runAllMigrationsWithStreaming')->andReturn(0);
    });

    test()->mock(StreamedCommandsService::class, function (MockInterface $mock) use ($seedCreatesUser): void {
        $mock->shouldReceive('streamArtisanCommand')->andReturnUsing(function () use ($seedCreatesUser): int {
            if ($seedCreatesUser) {
                User::factory()->create();
            }

            return 0;
        });
    });

    test()->mock(AddonCacheRefresher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refresh');
    });
}

it('sends the operator to the login page when the seed already created a user', function (): void {
    mockInstallRun(seedCreatesUser: true);

    $component = Livewire::test(Installer::class)->call('runMigrations');

    $component
        ->assertNotDispatched('installer-migrations-complete')
        ->assertSet('migrationOutput', fn (string $output): bool => str_contains($output, __('installer.user_already_seeded')));

    expect(collect($component->effects['xjs'] ?? [])->pluck('expression')->implode(' '))->toContain(json_encode(route('login')));
});

it('counts down to the user and airline step when the seed created no user', function (): void {
    mockInstallRun(seedCreatesUser: false);

    $component = Livewire::test(Installer::class)->call('runMigrations');

    $component
        ->assertDispatched('installer-migrations-complete')
        ->assertSet('migrationOutput', fn (string $output): bool => !str_contains($output, __('installer.user_already_seeded')));

    expect($component->effects['xjs'] ?? [])->toBeEmpty();
});
