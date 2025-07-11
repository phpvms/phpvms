<?php

namespace App\Filament\Pages;

use App\Repositories\KvpRepository;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use App\Services\VersionService;
use App\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class Maintenance extends Page
{
    use HasPageShield;

    protected static ?string $navigationGroup = 'Config';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.maintenance';

    public function forceUpdateCheckAction(): Action
    {
        return Action::make('forceUpdateCheck')->label('Force Update Check')->icon('heroicon-o-arrow-path')->action(function (): void {
            app(VersionService::class)->isNewVersionAvailable();

            $kvpRepo = app(KvpRepository::class);

            $new_version_avail = $kvpRepo->get('new_version_available', false);
            $new_version_tag = $kvpRepo->get('latest_version_tag');

            Log::info('Force check, available='.$new_version_avail.', tag='.$new_version_tag);

            if (!$new_version_avail) {
                Notification::make()
                    ->title('No new version available')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('New version available: '.$new_version_tag)
                    ->success()
                    ->send();
            }
        });
    }

    public function webCronEnable(): Action
    {
        return Action::make('webCronEnable')->label('Enable/Change ID')->action(function (): void {
            $id = Utils::generateNewId(24);
            setting_save('cron.random_id', $id);

            // Remove the webcron id from cache
            $cache = config('cache.keys.SETTINGS');
            Cache::forget($cache['key'].'cron.random_id');

            Notification::make()
                ->title('Web cron refreshed!')
                ->success()
                ->send();
        });
    }

    public function webCronDisable(): Action
    {
        return Action::make('webCronDisable')->label('Disable')->color('warning')->action(function (): void {
            setting_save('cron.random_id', '');

            // Remove the webcron id from cache
            $cache = config('cache.keys.SETTINGS');
            Cache::forget($cache['key'].'cron.random_id');

            Notification::make()
                ->title('Web cron disabled!')
                ->success()
                ->send();
        });
    }

    public function clearCaches(): Action
    {
        return Action::make('clearCaches')->icon('heroicon-o-trash')->label('Clear Cache')->action(function (array $arguments): void {
            $calls = [];
            $type = $arguments['type'];

            $theme_cache_file = base_path().'/bootstrap/cache/themes.php';
            $module_cache_files = base_path().'/bootstrap/cache/*_module.php';

            // When clearing the application, clear the config and the app itself
            if ($type === 'application' || $type === 'all') {
                $calls[] = 'config:cache';
                $calls[] = 'cache:clear';
                $calls[] = 'route:cache';
                $calls[] = 'clear-compiled';
                $calls[] = 'filament:clear-cached-components';

                $files = File::glob($module_cache_files);
                foreach ($files as $file) {
                    $module_cache = File::delete($file) ? 'Module cache file deleted' : 'Module cache file not found!';
                    Log::debug($module_cache.' | '.$file);
                }
            }

            // If we want to clear only the views but keep everything else
            if ($type === 'views' || $type === 'all') {
                $calls[] = 'view:clear';

                $theme_cache = unlink($theme_cache_file) ? 'Theme cache file deleted' : 'Theme cache file not found!';
                Log::debug($theme_cache.' | '.$theme_cache_file);
            }

            foreach ($calls as $call) {
                Artisan::call($call);
            }

            Notification::make()
                ->title('Cache cleared!')
                ->success()
                ->send();
        });
    }

    public function flushQueue(): Action
    {
        return Action::make('flushQueue')->icon('heroicon-o-trash')->label('Flush Failed Jobs')->action(function (): void {
            Artisan::call('queue:flush');

            Notification::make()
                ->title('Failed jobs flushed!')
                ->success()
                ->send();
        });
    }

    public function reseed(): Action
    {
        return Action::make('reseed')->icon('heroicon-o-circle-stack')->label('Rerun seeding')->action(function (): void {
            app(SeederService::class)->syncAllSeeds();

            Notification::make()
                ->title('Seeds synced successfully!')
                ->success()
                ->send();
        });
    }

    public function optimizeApp(): Action
    {
        return Action::make('optimizeApp')->icon('heroicon-o-wrench-screwdriver')->label('Optimize App')->action(function (): void {
            $calls = [
                // 'icons:cache',
                'filament:cache-components',
                'optimize',
            ];

            foreach ($calls as $call) {
                Artisan::call($call);
            }

            Notification::make()
                ->title('Application optimized!')
                ->success()
                ->send();
        });
    }

    public function update(): Action
    {
        return Action::make('update')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->label('Update App')
            ->visible(fn (): bool => app(InstallerService::class)->isUpgradePending())
            ->action(function (): void {
                Log::info('Update: run_migrations');

                $migrationSvc = app(MigrationService::class);
                $seederSvc = app(SeederService::class);

                $migrationsPending = $migrationSvc->migrationsAvailable();
                $dataMigrationsPending = $migrationSvc->dataMigrationsAvailable();

                if (count($migrationsPending) === 0 && count($dataMigrationsPending) === 0) {
                    $seederSvc->syncAllSeeds();
                    Notification::make()
                        ->title('Application updated successfully')
                        ->body('See logs for details')
                        ->success()
                        ->send();

                    return;
                }

                if (count($migrationsPending) !== 0) {
                    $migrationSvc->runAllMigrations();
                }
                $seederSvc->syncAllSeeds();

                if (count($dataMigrationsPending) !== 0) {
                    $migrationSvc->runAllDataMigrations();
                }

                Notification::make()
                    ->title('Application updated successfully')
                    ->body('See logs for details')
                    ->success()
                    ->send();
            });
    }
}
