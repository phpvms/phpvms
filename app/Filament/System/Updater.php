<?php

namespace App\Filament\System;

use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Filament\Facades\Filament;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class Updater extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.system.updater';

    protected static ?string $slug = 'update';

    public ?string $notes;

    public ?string $details;

    /**
     * Called whenever the component is loaded
     */
    public function mount(): void
    {
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
            abort_if(!Auth::user()?->can('admin_access'), 403);
        }

        if (!app(InstallerService::class)->isUpgradePending()) {
            Notification::make()
                ->title('phpVMS is already up to date')
                ->danger()
                ->send();

            $this->redirect(Filament::getDefaultPanel()->getUrl());

            return;
        }

        $this->fillForm();
    }

    /**
     * To fill the form (set default values)
     */
    public function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill();

        $this->callHook('afterFill');
    }

    /**
     * The filament form
     */
    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Before Update')->schema([

                ])->afterValidation(
                    function () {
                        $this->dispatch('start-migrations');
                    }
                ),
                Step::make('Update')
                    ->schema([
                        ViewField::make('details')
                            ->view('filament.system.migrations_details'),
                    ]),
            ])
                ->submitAction(new HtmlString(Blade::render(
                    <<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Finish Update
                    </x-filament::button>
                BLADE
                ))),
        ]);
    }

    /**
     * Migrate the database
     */
    public function migrate(): void
    {
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

            $this->redirect('/admin');

            return;
        }
        $output = '';
        if (count($migrationsPending) !== 0) {
            $output .= $migrationSvc->runAllMigrations();
        }
        $seederSvc->syncAllSeeds();

        if (count($dataMigrationsPending) !== 0) {
            $output .= $migrationSvc->runAllDataMigrations();
        }

        $this->dispatch('migrations-completed', message: $output);
    }

    /**
     * Called when the form is filed (ie update completed)
     */
    public function save(): void
    {
        $this->redirect('/admin');
    }
}
