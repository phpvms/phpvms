<?php

namespace App\Filament\System;

use App\Filament\Infolists\Components\StreamEntry;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use App\Services\Installer\StreamedCommandsService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use function Illuminate\Support\defer;

class Updater extends Page
{
    protected static ?string $slug = 'update';

    private string $stream = 'console_output';

    public function content(FilamentSchema $schema): FilamentSchema
    {
        return $schema->components([
            StreamEntry::make('output')
                ->state(fn () => __('installer.click_update_to_run'))
                ->afterLabel($this->update())
                ->label(__('installer.output'))
                ->viewData([
                    'stream' => $this->stream,
                ]),
        ]);
    }

    /**
     * Called whenever the component is loaded
     */
    public function mount(): void
    {
        // We do this when the component is loaded instead of in canAccess()
        // That's because canAccess() is called whenever a component of the panel is called (for navigation)
        // Or we can't connect to the db when loading the installer

        // Custom permission check (to support both v7 and v8 db)
        // v7
        if (Schema::hasTable('role_user')) {
            $result = DB::table('role_user')
                ->where('user_id', Auth::id())
                ->where('roles.name', 'LIKE', '%admin%')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->count();

            abort_if($result === 0, 403);
        } else { // v8
            abort_if(!Auth::user()?->can('access_admin'), 403);
        }

        if (!app(InstallerService::class)->isUpgradePending()) {
            Notification::make()
                ->title(__('filament.maintenance_database_is_up_to_date'))
                ->danger()
                ->send();

            $this->redirect(Filament::getDefaultPanel()->getUrl());
        }
    }

    public function update(): Action
    {
        return Action::make('update')
            ->label(__('installer.update'))
            ->action(function () {
                $this->stream(to: $this->stream, content: PHP_EOL.__('installer.starting_migration_process').PHP_EOL);

                $migrationSvc = app(MigrationService::class);
                $seederSvc = app(SeederService::class);

                $migrationsPending = $migrationSvc->migrationsAvailable();
                $dataMigrationsPending = $migrationSvc->dataMigrationsAvailable();

                $streamCallback = function (string $buffer) {
                    $this->stream(to: $this->stream, content: $buffer.PHP_EOL);
                };

                if (count($migrationsPending) !== 0) {
                    if (function_exists('proc_open') && false) {
                        // Streaming the output of the command is only available with proc_open (relies on Symfony Process)
                        $migrationSvc->runAllMigrationsWithStreaming($streamCallback);
                    } else {
                        $migrationSvc->runAllMigrations();
                    }
                }

                $seederSvc->syncAllSeeds();

                if (count($dataMigrationsPending) !== 0) {
                    if (function_exists('proc_open') && false) {
                        // Streaming the output of the command is only available with proc_open (relies on Symfony Process)
                        $migrationSvc->runAllDataMigrationsWithStreaming($streamCallback);
                    } else {
                        $migrationSvc->runAllDataMigrations();
                    }
                }

                $this->stream(to: $this->stream, content: __('installer.migrations_completed').PHP_EOL.__('installer.lets_rebuild_cache').PHP_EOL);

                if (function_exists('proc_open') && false) {
                    // Streaming the output of the command is only available with proc_open (relies on Symfony Process)
                    app(StreamedCommandsService::class)->streamArtisanCommand(['optimize:clear'], $streamCallback);
                    app(StreamedCommandsService::class)->streamArtisanCommand(['optimize'], $streamCallback);
                } else {
                    $this->stream($this->stream, PHP_EOL.__('installer.cache_build_background').PHP_EOL);

                    // Clearing the cache immediately sends the response, thus killing the request. So we defer it, it's executed at the end of the request in the background.
                    defer(function () {
                        Artisan::call('optimize:clear');
                        $clearOutput = Artisan::output();

                        Artisan::call('optimize');
                        $optimizeOutput = Artisan::output();

                        // Combine both outputs for better logging
                        $output = "Optimize:clear Output:\n".$clearOutput."\nOptimize Output:\n".$optimizeOutput;

                        Log::info('Optimized cache successfully', ['output' => $output]);
                    });
                }

                $this->stream($this->stream, PHP_EOL.__('installer.update_completed').PHP_EOL);
                sleep(10);
                $this->redirect(Filament::getDefaultPanel()->getUrl());
            });
    }

    public function getTitle(): string
    {
        return __('installer.update_phpvms');
    }
}
