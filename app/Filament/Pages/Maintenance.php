<?php

namespace App\Filament\Pages;

use App\Models\Enums\NavigationGroup;
use App\Repositories\KvpRepository;
use App\Services\CronService;
use App\Services\Installer\InstallerService;
use App\Services\Installer\SeederService;
use App\Services\VersionService;
use App\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class Maintenance extends Page
{
    public array $cron = [];

    use HasPageShield;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Developers;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    // protected string $view = 'filament.pages.maintenance';

    public function content(Schema $schema): Schema
    {
        $this->cron = [
            'command'   => app(CronService::class)->getCronExecString(),
            'random_id' => empty(setting('cron.random_id')) ? __('common.disabled') : url(route('api.maintenance.cron', setting('cron.random_id'))),
        ];

        $cronProblemExists = app(CronService::class)->cronProblemExists();

        return $schema->components([

            Section::make()
                ->schema([
                    Flex::make([
                        $this->updateDatabase(),
                        $this->checkForPhpVMSUpdates(),
                        $this->resyncAllSeeds(),
                        $this->flushFailedJobs(),
                    ]),
                    Flex::make([
                        $this->optimizeApp(),
                        $this->clearCache(),
                    ]),
                ]),

            Section::make(__('filament.maintenance_cron_setup'))
                ->statePath('cron')
                ->schema([
                    TextEntry::make(__('filament.maintenance_cron_run_recently'))
                        ->color( $cronProblemExists ? 'danger' : 'success')
                        ->state(fn () => $cronProblemExists ? __('common.no') : __('common.yes')),

                    TextInput::make('command')
                        ->label(__('filament.maintenance_cron_command'))
                        ->hintAction(
                            Action::make('openDocs')
                                ->label(__('common.see_the_docs'))
                                ->url(docs_link('cron'), shouldOpenInNewTab: true)
                        )
                        ->helperText(__('filament.maintenance_cron_command_hint'))
                        ->disabled(),

                    TextInput::make('random_id')
                        ->label(__('filament.maintenance_cron_web_url'))
                        ->hintActions([
                            $this->enableWebCron(),
                            $this->disableWebCron(),
                        ])
                        ->helperText(__('filament.maintenance_cron_web_url_hint'))
                        ->disabled(),
                ]),
        ]);
    }

    public function checkForPhpVMSUpdates(): Action
    {
        return Action::make('checkForPhpVMSUpdates')
            ->label(__('filament.maintenance_check_update'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function () {
                app(VersionService::class)->isNewVersionAvailable();

                $kvpRepo = app(KvpRepository::class);

                $new_version_avail = $kvpRepo->get('new_version_available', false);
                $new_version_tag = $kvpRepo->get('latest_version_tag');

                $current_version = app(VersionService::class)->getCurrentVersion(include_build: false);

                Log::info('Force check, available='.$new_version_avail.', tag='.$new_version_tag);

                if (!$new_version_avail) {
                    Notification::make()
                        ->title(__('filament.maintenance_is_up_to_date', ['service' => 'phpVMS application']))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('filament.maintenance_new_version_available', ['version' => $new_version_tag]))
                        ->body(__('filament.maintenance_current_version', ['version' => $current_version]))
                        ->success()
                        ->send();
                }
            });
    }

    public function enableWebCron(): Action
    {
        return Action::make('enableWebCron')
            ->label(__('filament.maintenance_cron_change_id'))
            ->action(function () {
                $id = Utils::generateNewId(24);
                setting_save('cron.random_id', $id);
                $this->cron['random_id'] = url(route('api.maintenance.cron', $id));

                // Remove the webcron id from cache
                $cache = config('cache.keys.SETTINGS');
                Cache::forget($cache['key'].'cron.random_id');

                Notification::make()
                    ->title(__('filament.maintenance_cron_web_updated'))
                    ->success()
                    ->send();
            });
    }

    public function disableWebCron(): Action
    {
        return Action::make('disableWebCron')
            ->label(__('common.disable'))
            ->color('warning')
            ->action(function () {
                setting_save('cron.random_id', '');

                $this->cron['random_id'] = __('common.disabled');

                // Remove the webcron id from cache
                $cache = config('cache.keys.SETTINGS');
                Cache::forget($cache['key'].'cron.random_id');

                Notification::make()
                    ->title(__('filament.maintenance_cron_web_updated'))
                    ->success()
                    ->send();
            });
    }

    public function clearCache(): Action
    {
        return Action::make('clearCache')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->label(__('filament.maintenance_clear_cache'))
            ->action(function () {
                $calls = [
                    'optimize:clear',
                    'cache:clear',
                ];

                $theme_cache_file = base_path().'/bootstrap/cache/themes.php';
                $module_cache_files = base_path().'/bootstrap/cache/*_module.php';

                // Let's clear the module cache files
                $files = File::glob($module_cache_files);
                foreach ($files as $file) {
                    $module_cache = File::delete($file) ? 'Module cache file deleted' : 'Module cache file not found!';
                    Log::debug($module_cache.' | '.$file);
                }

                foreach ($calls as $call) {
                    Artisan::call($call);
                }

                Notification::make()
                    ->title(__('filament.maintenance_cache_cleared'))
                    ->body(__('filament.maintenance_recommend_optimize'))
                    ->success()
                    ->send();
            });
    }

    public function flushFailedJobs(): Action
    {
        return Action::make('flushFailedJobs')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->label(__('filament.maintenance_flush_failed_jobs'))
            ->action(function () {
                Artisan::call('queue:flush');

                Notification::make()
                    ->title(__('filament.maintenance_failed_jobs_flushed'))
                    ->success()
                    ->send();
            });
    }

    public function resyncAllSeeds(): Action
    {
        return Action::make('resyncAllSeeds')
            ->icon(Heroicon::OutlinedCircleStack)
            ->color('warning')
            ->label(__('filament.maintenance_resync_all_seeds'))
            ->action(function () {
                app(SeederService::class)->syncAllSeeds();

                Notification::make()
                    ->title(__('filament.maintenance_seeds_resynced'))
                    ->success()
                    ->send();
            });
    }

    public function optimizeApp(): Action
    {
        return Action::make('optimizeApp')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->label(__('filament.maintenance_optimize_app'))
            ->action(function () {
                Artisan::call('optimize');

                Notification::make()
                    ->title(__('filament.maintenance_app_optimized'))
                    ->success()
                    ->send();
            });
    }

    public function updateDatabase(): Action
    {
        $upgradePending = app(InstallerService::class)
            ->isUpgradePending();

        return Action::make('updateDatabase')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->extraAttributes(['style' => 'text-align: center;'])
            ->label($upgradePending ? __('filament.maintenance_update_database') : __('filament.maintenance_database_is_up_to_date'))
            ->disabled(!$upgradePending)
            ->url('/'); // TODO: link to the system page
    }
}
