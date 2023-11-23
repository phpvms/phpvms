<?php

namespace App\Filament\Pages;

use App\Repositories\KvpRepository;
use App\Services\VersionService;
use App\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
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
        return Action::make('forceUpdateCheck')->label('Force Update Check')->icon('heroicon-o-arrow-path')->action(function () {
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
        return Action::make('webCronEnable')->label('Enable/Change ID')->action(function () {
            $id = Utils::generateNewId(24);
            setting_save('cron.random_id', $id);

            Notification::make()
                ->title('Web cron refreshed!')
                ->success()
                ->send();
        });
    }

    public function webCronDisable(): Action
    {
        return Action::make('webCronDisable')->label('Disable')->color('warning')->action(function () {
            setting_save('cron.random_id', '');

            Notification::make()
                ->title('Web cron disabled!')
                ->success()
                ->send();
        });
    }

    public function clearCaches(): Action
    {
        return Action::make('clearCaches')->icon('heroicon-o-trash')->label('Clear Cache')->action(function (array $arguments) {
            $calls = [];
            $type = $arguments['type'];

            // When clearing the application, clear the config and the app itself
            if ($type === 'application' || $type === 'all') {
                $calls[] = 'config:cache';
                $calls[] = 'cache:clear';
                $calls[] = 'route:cache';
                $calls[] = 'clear-compiled';
            }

            // If we want to clear only the views but keep everything else
            if ($type === 'views' || $type === 'all') {
                $calls[] = 'view:clear';
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
}
