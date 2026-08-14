<?php

declare(strict_types=1);

use App\Filament\System\Updater;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use App\Services\Installer\StreamedCommandsService;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;
use Mockery\MockInterface;

/**
 * The updater streams its console log with wire:stream, which only lives until
 * the request's re-render — the log must ALSO be accumulated into
 * $updateOutput, or the finished page morphs back to an empty state.
 *
 * Every service is mocked: the real ones shell out to `php artisan` and the
 * subprocess would run against the development database, not phpunit's sqlite.
 */
it('keeps the streamed update log in component state after the run', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $this->mock(InstallerService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isUpgradePending')->andReturnTrue();
        $mock->shouldReceive('ensurePassportKeys');
    });

    $this->mock(MigrationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('migrationsAvailable')->andReturn(['2099_01_01_000000_example']);
        $mock->shouldReceive('dataMigrationsAvailable')->andReturn([]);
        $mock->shouldReceive('runAllMigrationsWithStreaming')
            ->andReturnUsing(function (callable $callback): void {
                $callback('Migrating: 2099_01_01_000000_example');
            });
    });

    $this->mock(SeederService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('syncAllSeeds');
    });

    $this->mock(StreamedCommandsService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('streamArtisanCommand');
    });

    Livewire::test(Updater::class)
        ->call('runUpdate')
        ->assertSet('updateStarted', true)
        ->assertSet('updateOutput', fn (string $output): bool => str_contains($output, 'Migrating: 2099_01_01_000000_example')
            && str_contains($output, __('installer.update_completed')))
        // The persisted log is what the post-run re-render actually shows.
        ->assertSee('Migrating: 2099_01_01_000000_example');
});
