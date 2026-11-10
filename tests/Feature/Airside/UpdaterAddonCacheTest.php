<?php

declare(strict_types=1);

use App\Addons\Support\OctaneReloader;
use App\Filament\System\Updater;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use App\Services\Installer\StreamedCommandsService;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;
use Mockery\MockInterface;

/**
 * A finished update reloads the workers after `optimize` has rebuilt the addon
 * boot cache, so a bundled theme the release adds is registered without a
 * manual step.
 */
function mockUpdaterServices(): void
{
    test()->mock(InstallerService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isUpgradePending')->andReturnTrue();
        $mock->shouldReceive('ensurePassportKeys');
        $mock->shouldReceive('linkStorage')->andReturnTrue();
    });
    test()->mock(MigrationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('migrationsAvailable')->andReturn([]);
        $mock->shouldReceive('dataMigrationsAvailable')->andReturn([]);
    });
    test()->mock(SeederService::class, fn (MockInterface $mock) => $mock->shouldReceive('syncAllSeeds'));
}

it('reloads the workers once the update has finished', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    mockUpdaterServices();

    $this->mock(StreamedCommandsService::class, fn (MockInterface $mock) => $mock->shouldReceive('streamArtisanCommand')->andReturn(0));
    $this->mock(OctaneReloader::class, fn (MockInterface $mock) => $mock->shouldReceive('reload')->once());

    Livewire::test(Updater::class)->call('runUpdate');
});

it('does not reload the workers when the update fails', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    mockUpdaterServices();

    $this->mock(StreamedCommandsService::class, fn (MockInterface $mock) => $mock->shouldReceive('streamArtisanCommand')->andReturn(1));
    $this->mock(OctaneReloader::class, fn (MockInterface $mock) => $mock->shouldNotReceive('reload'));

    Livewire::test(Updater::class)->call('runUpdate');
});
