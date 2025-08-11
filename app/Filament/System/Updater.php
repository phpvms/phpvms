<?php

namespace App\Filament\System;

use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Updater extends Page
{
    protected static ?string $slug = 'update';

    public $defaultAction = 'update';

    private string $stream = 'console_output';

    public function content(FilamentSchema $schema): FilamentSchema
    {
        return $schema->components([
            ViewEntry::make('output')
                ->label(__('installer.output'))
                ->view('filament.system.stream', [
                    'stream' => $this->stream,
                ]),
        ]);
    }

    /**
     * Called whenever the component is loaded
     */
    public function mount(): void
    {
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
            ->action(function () {
                $this->stream(to: $this->stream, content: 'Starting update process...');

                $migrationSvc = app(MigrationService::class);
                $seederSvc = app(SeederService::class);

                $migrationsPending = $migrationSvc->migrationsAvailable();
                $dataMigrationsPending = $migrationSvc->dataMigrationsAvailable();

                if (count($migrationsPending) !== 0) {
                    $migrationSvc->runAllMigrationsWithStreaming(function (string $buffer) {
                        $this->stream(to: $this->stream, content: $buffer);
                    });
                }

                $seederSvc->syncAllSeeds();

                if (count($dataMigrationsPending) !== 0) {
                    $migrationSvc->runAllDataMigrationsWithStreaming(function (string $buffer) {
                        $this->stream(to: $this->stream, content: $buffer);
                    });
                }

                $this->stream($this->stream, 'Update completed, you\'ll be redirected in 10 seconds...');
                sleep(10);
                $this->redirect(Filament::getDefaultPanel()->getUrl());
            });
    }

    public static function canAccess(): bool
    {
        // Custom permission check (to support both v7 and v8 db)
        // v7
        if (Schema::hasTable('role_user')) {
            $result = DB::table('role_user')
                ->where('user_id', Auth::id())
                ->where('roles.name', 'LIKE', '%admin%')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->count();

            return $result > 0;
        } else { // v8
            return Auth::user()?->can('admin_access');
        }
    }

    public function getTitle(): string
    {
        return __('installer.updater.title');
    }
}
