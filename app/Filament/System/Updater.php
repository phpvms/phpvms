<?php

namespace App\Filament\System;

use App\Filament\Infolists\Components\StreamEntry;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

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
                    $migrationSvc->runAllMigrationsWithStreaming($streamCallback);
                }

                $seederSvc->syncAllSeeds();

                if (count($dataMigrationsPending) !== 0) {
                    $migrationSvc->runAllDataMigrationsWithStreaming($streamCallback);
                }

                $this->stream(to: $this->stream, content: __('installer.migrations_completed').PHP_EOL.__('installer.lets_rebuild_cache').PHP_EOL);

                $finder = new PhpExecutableFinder();
                $php_path = $finder->find(false);
                $php = str_replace('-fpm', '', $php_path);

                // If this is the cgi version of the exec, add this arg, otherwise there's
                // an error with no arguments existing
                if (str_contains($php, '-cgi')) {
                    $php .= ' -d register_argc_argv=On';
                }

                $artisan = base_path('artisan');
                $commands = [
                    [$php, $artisan, 'optimize:clear'],
                    [$php, $artisan, 'optimize'],
                ];

                foreach ($commands as $command) {
                    $process = new Process($command);
                    $process->setTimeout(120);
                    $process->run(function (string $type, string $buffer) use ($streamCallback) {
                        $streamCallback($buffer);
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
