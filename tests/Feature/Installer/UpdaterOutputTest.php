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
            ->andReturnUsing(function (callable $callback): int {
                $callback('Migrating: 2099_01_01_000000_example');

                return 0;
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

it('stops the update when a streamed command fails', function (string $failedCommand): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $this->mock(InstallerService::class, function (MockInterface $mock) use ($failedCommand): void {
        $mock->shouldReceive('isUpgradePending')->andReturnTrue();
        if ($failedCommand === 'migrate') {
            $mock->shouldNotReceive('ensurePassportKeys');
        } else {
            $mock->shouldReceive('ensurePassportKeys')->once();
        }
    });

    $this->partialMock(MigrationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('migrationsAvailable')->andReturn(['schema-migration']);
        $mock->shouldReceive('dataMigrationsAvailable')->andReturn(['data-migration']);
        $mock->shouldReceive('getMigrationPaths')->andReturn([]);
    });

    $this->mock(SeederService::class, function (MockInterface $mock) use ($failedCommand): void {
        if ($failedCommand === 'migrate') {
            $mock->shouldNotReceive('syncAllSeeds');
        } else {
            $mock->shouldReceive('syncAllSeeds')->once();
        }
    });

    $this->mock(StreamedCommandsService::class, function (MockInterface $mock) use ($failedCommand): void {
        foreach (['migrate', 'migrate-data', 'optimize:clear', 'optimize'] as $command) {
            $mock->shouldReceive('streamArtisanCommand')
                ->once()
                ->ordered()
                ->withArgs(fn (array $args, Closure $callback): bool => $args[0] === $command)
                ->andReturnUsing(function (array $args, Closure $callback) use ($command, $failedCommand): int {
                    $callback($command === $failedCommand ? 'Command failed' : 'Command succeeded');

                    return $command === $failedCommand ? 1 : 0;
                });
            if ($command === $failedCommand) {
                break;
            }
        }
    });

    $component = Livewire::test(Updater::class)
        ->call('runUpdate')
        ->assertSet('updateOutput', fn (string $output): bool => str_contains($output, 'Command failed')
            && !str_contains($output, __('installer.update_completed')));

    expect($component->effects['xjs'] ?? [])->toBeEmpty();
})->with(['migrate', 'migrate-data', 'optimize:clear', 'optimize']);
