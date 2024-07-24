<?php

namespace App\Filament\System;

use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class Updater extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.system.updater';

    protected static ?string $slug = 'update';

    public ?string $notes;
    public ?string $details;

    public function mount()
    {
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

    public function fillForm()
    {
        $this->callHook('beforeFill');

        $this->form->fill();

        $this->callHook('afterFill');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Before Update')->schema([

                ])->afterValidation(
                    function () {
                        $this->dispatch('start-migrations');
                    }
                ),
                Forms\Components\Wizard\Step::make('Update')
                    ->schema([
                        Forms\Components\ViewField::make('details')
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

    public function migrate()
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

    public function save()
    {
        $this->redirect('/admin');
    }
}
