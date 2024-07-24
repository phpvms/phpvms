<?php

namespace App\Filament\System;

use App\Models\User;
use App\Services\Installer\DatabaseService;
use App\Services\LegacyImporterService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class LegacyImporter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.system.legacy-importer';

    protected static ?string $slug = 'legacy-import';

    public ?string $notes;
    public ?array $db;
    public ?string $details;

    public function mount()
    {
        if (Schema::hasTable('users') && User::count() > 0) {
            if (!Auth::check()) {
                $this->redirect(url('/login'));
            } else {
                abort_if(!Auth::user()?->can('admin_access'), 403);
            }
        }
        // If db has users, then authorize only admin

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
                Forms\Components\Wizard\Step::make('Important Notes')->schema([
                    Forms\Components\ViewField::make('notes')
                        ->view('filament.system.legacy_importer_notes'),
                ]),
                Forms\Components\Wizard\Step::make('Legacy Database Config')
                    ->schema([
                        Forms\Components\Group::make()
                            ->statePath('db')
                            ->columns()
                            ->schema([
                                Forms\Components\Select::make('db_conn')
                                    ->hint('Enter details about your legacy phpVMS Database')
                                    ->label('Database Type')
                                    ->required()
                                    ->live()
                                    ->options(['mysql' => 'mysql', 'mariadb' => 'mariadb', 'sqlite' => 'sqlite']),

                                Forms\Components\TextInput::make('db_prefix')
                                    ->string()
                                    ->default('phpvms_')
                                    ->hint('Prefix of the tables, if you\'re using one')
                                    ->label('Database Prefix'),

                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('db_host')
                                        ->label('Database Host')
                                        ->required()
                                        ->string()
                                        ->hintAction(
                                            Forms\Components\Actions\Action::make('testDb')
                                                ->label('Test Database Credentials')
                                                ->action(fn () => $this->testDb())
                                        )
                                        ->default('localhost'),

                                    Forms\Components\TextInput::make('db_port')
                                        ->label('Database Port')
                                        ->required()
                                        ->numeric()
                                        ->default('3306'),

                                    Forms\Components\TextInput::make('db_name')
                                        ->required()
                                        ->string()
                                        ->label('Database Name'),

                                    Forms\Components\TextInput::make('db_user')
                                        ->required()
                                        ->string()
                                        ->label('Database User'),

                                    Forms\Components\TextInput::make('db_pass')
                                        ->password()
                                        ->revealable()
                                        ->label('Database Password'),
                                ])
                                    ->visible(fn (
                                        Forms\Get $get
                                    ): bool => $get('db_conn') && $get('db_conn') !== 'sqlite')
                                    ->columns()
                                    ->columnSpanFull(),
                            ]),
                    ])->afterValidation(
                        function () {
                            $this->dbSetup();
                        }
                    ),

                Forms\Components\Wizard\Step::make('Import')
                    ->schema([
                        Forms\Components\ViewField::make('details')
                            ->view('filament.system.legacy_importer_details'),
                    ]),
            ])
                ->submitAction(new HtmlString(Blade::render(
                    <<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Complete Import
                    </x-filament::button>
                BLADE
                ))),
        ]);
    }

    private function testDb(): bool
    {
        $data = $this->db ?? [];

        try {
            app(DatabaseService::class)->checkDbConnection(
                $data['db_conn'],
                $data['db_host'],
                $data['db_port'],
                $data['db_name'],
                $data['db_user'],
                $data['db_pass']
            );
        } catch (\Exception $e) {
            Log::error('Testing db failed');
            Log::error($e->getMessage());

            Notification::make()
                ->title('Database connection failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            return false;
        }

        Notification::make()
            ->title('Database Connection Looks Good')
            ->success()
            ->send();

        return true;
    }

    private function dbSetup()
    {
        $data = $this->db ?? [];

        if (!$this->testDb()) {
            throw new Halt();
        }

        $creds = [
            'host'         => $data['db_host'],
            'port'         => $data['db_port'],
            'name'         => $data['db_name'],
            'user'         => $data['db_user'],
            'pass'         => $data['db_pass'],
            'table_prefix' => $data['db_prefix'],
        ];

        try {
            // Save creds for later
            app(LegacyImporterService::class)->saveCredentials($creds);
        } catch (\Exception $e) {
            Log::error('Legacy Importer Error: '.$e->getMessage(), $e->getTrace());

            Notification::make()
                ->title('Error while setting up importer: '.$e->getMessage())
                ->body('Show logs for more details')
                ->danger()
                ->send();

            throw new Halt();
        }

        $this->dispatch('dbsetup-completed');
    }

    public function import(int $batch_index)
    {
        $manifest = app(LegacyImporterService::class)->generateImportManifest();

        if ($batch_index === 0) {
            Notification::make()
                ->title('Starting import')
                ->success()
                ->send();
        } elseif ($batch_index >= count($manifest)) {
            Notification::make()
                ->title('Import completed')
                ->success()
                ->send();
            return;
        }

        try {
            $batch = $manifest[$batch_index];

            Log::info('Starting stage '.$batch['importer'].' from offset '.$batch['start']);

            app(LegacyImporterService::class)->run($batch['importer'], $batch['start']);

            $this->dispatch('import-update', completed: $batch_index * 100 / count($manifest), error: false, message: $batch['message'], nextIndex: $batch_index + 1);
        } catch (\Exception $e) {
            Log::error('Legacy Importer Error: '.$e->getMessage(), $e->getTrace());

            $this->dispatch('import-update', completed: $batch_index * 100 / count($manifest), error: true, message: 'Legacy Importer Error: '.$e->getMessage());
        }
    }

    public function save()
    {
        $this->redirect('/admin');
    }
}
