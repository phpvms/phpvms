<?php

namespace App\Filament\System;

use App\Filament\Infolists\Components\StreamEntry;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use App\Services\Installer\StreamedCommandsService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;

class Updater extends Page
{
    protected static ?string $slug = 'update';

    private string $stream = 'console_output';

    public bool $updateStarted = false;

    public string $updateOutput = '';

    #[Override]
    public function content(FilamentSchema $schema): FilamentSchema
    {
        return $schema->components([
            StreamEntry::make('output')
                ->state(fn (): string => $this->updateOutput)
                ->label(__('installer.output'))
                ->extraAttributes($this->updateStarted ? [] : ['wire:init' => 'runUpdate'])
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

        $this->authorizeUpdate();

        if (!app(InstallerService::class)->isUpgradePending()) {
            Notification::make()
                ->title(__('filament.maintenance_database_is_up_to_date'))
                ->danger()
                ->send();

            $this->redirect(Filament::getDefaultPanel()->getUrl());
        }
    }

    /**
     * Admin permission check (supports both v7 and v8 schemas).
     *
     * Lives outside mount() so public Livewire actions can re-assert it — mount()
     * only runs on initial page load, not on subsequent wire calls.
     */
    private function authorizeUpdate(): void
    {
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
    }

    /**
     * Runs migrations, seeds, data migrations, and cache rebuild — streaming output.
     * Idempotent within a single Livewire lifecycle via $updateStarted.
     */
    public function runUpdate(): void
    {
        $this->authorizeUpdate();

        if ($this->updateStarted) {
            return;
        }

        $this->updateStarted = true;

        $this->stream(content: PHP_EOL.__('installer.starting_migration_process').PHP_EOL, to: $this->stream);

        $migrationSvc = app(MigrationService::class);
        $seederSvc = app(SeederService::class);

        $migrationsPending = $migrationSvc->migrationsAvailable();
        $dataMigrationsPending = $migrationSvc->dataMigrationsAvailable();

        $streamCallback = function (string $buffer): void {
            $this->stream(content: $buffer.PHP_EOL, to: $this->stream);
        };

        if (count($migrationsPending) !== 0) {
            $migrationSvc->runAllMigrationsWithStreaming($streamCallback);
        }

        $seederSvc->syncAllSeeds();

        // Existing installs upgrading to Passport won't have signing keys yet;
        // generate them (idempotent) so the API keeps working post-upgrade.
        app(InstallerService::class)->ensurePassportKeys();

        if (count($dataMigrationsPending) !== 0) {
            $migrationSvc->runAllDataMigrationsWithStreaming($streamCallback);
        }

        $this->stream(content: __('installer.migrations_completed').PHP_EOL.__('installer.lets_rebuild_cache').PHP_EOL, to: $this->stream);

        app(StreamedCommandsService::class)->streamArtisanCommand(['optimize:clear'], $streamCallback);
        app(StreamedCommandsService::class)->streamArtisanCommand(['optimize'], $streamCallback);

        $this->stream(content: PHP_EOL.__('installer.update_completed').PHP_EOL, to: $this->stream);

        $panelUrl = Filament::getDefaultPanel()->getUrl();
        $this->js('setTimeout(() => window.location.href = '.json_encode($panelUrl).', 10000)');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('installer.update_phpvms');
    }
}
