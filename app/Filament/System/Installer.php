<?php

namespace App\Filament\System;

use App\Database\seeds\ShieldSeeder;
use App\Models\User;
use App\Services\AirlineService;
use App\Services\Installer\ConfigService;
use App\Services\Installer\DatabaseService;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\RequirementsService;
use App\Services\Installer\SeederService;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\Utils;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class Installer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.system.installer';

    protected static ?string $slug = 'install';

    public ?string $requirements;
    public ?string $details;
    public ?array $env;
    public ?array $user;

    public function mount()
    {
        if (!empty(config('app.key')) && config('app.key') !== 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=' && Schema::hasTable('users') && User::count() > 0) {
            Notification::make()
                ->title('phpVMS is already installed')
                ->danger()
                ->send();

            $this->redirect('/admin');
            return;
        }

        if (request()->get('step') === 'migrations') {
            $this->dispatch('start-migrations');
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
        $requirementsData = $this->getRequirementsData();

        return $form->schema([
            Wizard::make([
                Wizard\Step::make('Requirements')
                    ->schema([
                        ViewField::make('requirements')
                            ->view('filament.system.installer_requirements')
                            ->viewData($requirementsData)
                    ])
                    ->beforeValidation(function () use ($requirementsData) {
                        if (!$requirementsData['php']['passed'] || !$requirementsData['extensionsPassed'] || !$requirementsData['directoriesPassed']) {
                            throw new Halt();
                        }
                    }),

                Wizard\Step::make('Database Setup')->schema([
                    Section::make('Site Config')
                        ->statePath('env')
                        ->columns()
                        ->schema([
                            TextInput::make('site_name')
                                ->label('Site Name')
                                ->required()
                                ->string(),

                            TextInput::make('app_url')
                                ->label('Site URL')
                                ->required()
                                ->url()
                                ->default(request()->root()),
                        ]),

                    Section::make('Database Config')
                        ->statePath('env')
                        ->columns()
                        ->description('Enter the target database information')
                        ->schema([
                            Select::make('db_conn')
                                ->label('Database Type')
                                ->required()
                                ->live()
                                ->options(['mysql' => 'mysql', 'mariadb' => 'mariadb', 'sqlite' => 'sqlite']),

                            TextInput::make('db_prefix')
                                ->string()
                                ->hint('Set this if you\'re sharing the database with another application')
                                ->label('Database Prefix'),

                            Group::make([
                                TextInput::make('db_host')
                                    ->label('Database Host')
                                    ->required()
                                    ->string()
                                    ->hintAction(
                                        Action::make('testDb')
                                            ->label('Test Database Credentials')
                                            ->action(fn() => $this->testDb())
                                    )
                                    ->default('localhost'),

                                TextInput::make('db_port')
                                    ->label('Database Port')
                                    ->required()
                                    ->numeric()
                                    ->default('3306'),

                                TextInput::make('db_name')
                                    ->required()
                                    ->string()
                                    ->label('Database Name'),

                                TextInput::make('db_user')
                                    ->required()
                                    ->string()
                                    ->label('Database User'),

                                TextInput::make('db_pass')
                                    ->password()
                                    ->revealable()
                                    ->label('Database Password'),
                            ])
                                ->visible(fn(Get $get): bool => $get('db_conn') && $get('db_conn') !== 'sqlite')
                                ->columns()
                                ->columnSpanFull(),
                        ])
                ])->afterValidation(
                    function () {
                        $this->envAndDBSetup();
                    }
                ),

                Wizard\Step::make('Migrations')->schema([
                    ViewField::make('details')
                        ->view('filament.system.migrations_details')
                ])->afterValidation(function () {
                   if (count(app(MigrationService::class)->migrationsAvailable()) > 0) {
                       Notification::make()
                           ->title('Error: you have pending migrations')
                           ->danger()
                           ->send();

                       throw new Halt();
                   }
                }),

                Wizard\Step::make('User & Airline Setup')->schema([
                    Section::make('Airline Information')
                        ->statePath('user')
                        ->headerActions([
                            Action::make('test')
                                ->label('phpVMS v5 Legacy Importer')
                                ->url('/system/legacy'),
                        ])
                        ->schema([
                            TextInput::make('airline_icao')
                                ->length(3)
                                ->string()
                                ->required()
                                ->unique('airlines', 'icao')
                                ->label('Airline ICAO'),

                            TextInput::make('airline_name')
                                ->string()
                                ->required()
                                ->label('Airline Name'),

                            Select::make('airline_country')
                                ->options(Countries::getSelectList())
                                ->native(false)
                                ->required()
                                ->searchable(),
                        ])->columns(),

                    Section::make('Super Admin User Information')
                        ->statePath('user')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->string(),

                            TextInput::make('email')
                                ->unique('users', 'email')
                                ->required()
                                ->email(),

                            TextInput::make('password')
                                ->revealable()
                                ->password()
                                ->confirmed()
                                ->required(),

                            TextInput::make('password_confirmation')
                                ->password()
                                ->required()
                        ])->columns()
                ]),
            ])
                ->persistStepInQueryString()
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Complete Setup
                    </x-filament::button>
                BLADE
                )))
        ]);
    }

    private function getRequirementsData(): array
    {
        $reqSvc = app(RequirementsService::class);

        $php_version = $reqSvc->checkPHPVersion();
        $extensions = $reqSvc->checkExtensions();
        $directories = $reqSvc->checkPermissions();

        $extensionsPassed = $this->allPassed($extensions);
        $directoriesPassed = $this->allPassed($directories);

        return [
            'php' => $php_version,
            'extensions' => $extensions,
            'extensionsPassed' => $extensionsPassed,
            'directories' => $directories,
            'directoriesPassed' => $directoriesPassed,
        ];
    }

    private function allPassed(array $arr): bool
    {
        foreach ($arr as $item) {
            if ($item['passed'] === false) {
                return false;
            }
        }

        return true;
    }

    private function envAndDbSetup()
    {
        $log_str = $this->env ?? [];
        $log_str['db_pass'] = '';

        $data = $this->env ?? [];

        Log::info('ENV Setup', $log_str);

        if (!$this->testDb()) {
            throw new Halt();
        }

        // Now write out the env file
        $attrs = [
            'SITE_NAME' => $data['site_name'],
            'APP_URL' => $data['app_url'],
            'DB_CONNECTION' => $data['db_conn'],
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_name'],
            'DB_USERNAME' => $data['db_user'],
            'DB_PASSWORD' => $data['db_pass'],
            'DB_PREFIX' => $data['db_prefix'],
        ];

        /*
         * Create the config files and then redirect so that the
         * framework can pickup all those configs, etc, before we
         * setup the database and stuff
         */
        try {
            app(ConfigService::class)->createConfigFiles($attrs);
        } catch (\Exception $e) {
            Log::error('Config files failed to write');
            Log::error($e->getMessage());

            Notification::make()
                ->title('Failed to write config files')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }

        $this->dispatch('start-migrations');
    }

    public function migrate()
    {
        $console_out = '';

        try {
            $console_out .= app(DatabaseService::class)->setupDB();
            $console_out .= app(MigrationService::class)->runAllMigrations();
            app(SeederService::class)->syncAllSeeds();
            app(ShieldSeeder::class)->run();
        } catch (QueryException $e) {
            Log::error('Error on db setup: '.$e->getMessage());

            app(ConfigService::class)->removeConfigFiles();

            Notification::make()
                ->title('Error Setting Up Database')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Log::info('DB Setup Details', [$console_out]);
        $this->dispatch('migrations-completed', message: $console_out);
    }

    private function airlineAndUserSetup()
    {
        $data = $this->user ?? [];

        // Create the first airline
        $attrs = [
            'icao'    => $data['airline_icao'],
            'name'    => $data['airline_name'],
            'country' => $data['airline_country']
        ];

        $airline = app(AirlineService::class)->createAirline($attrs);

        /**
         * Create the user, and associate to the airline
         * Ensure the seed data at least has one airport
         * KAUS, for giggles, though.
         */
        $attrs = [
            'name'              => $data['name'],
            'email'             => $data['email'],
            'api_key'           => Utils::generateApiKey(),
            'airline_id'        => $airline->id,
            'password'          => Hash::make($data['password']),
            'opt_in'            => true,
            'email_verified_at' => now(),
        ];

        $user = app(UserService::class)->createUser($attrs, ['super_admin']);

        Log::info('First User Created: ', $user->toArray());

        // Set the initial admin e-mail address
        setting_save('general.admin_email', $user->email);
    }

    private function testDb(): bool
    {
        $data = $this->env ?? [];

        try {
            app(DatabaseService::class)->checkDbConnection($data['db_conn'], $data['db_host'], $data['db_port'],
                $data['db_name'], $data['db_user'], $data['db_pass']);
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


    public function save()
    {
        $this->validate();
        $this->airlineAndUserSetup();

        flash()->success('phpVMS Installation Completed Successfully');
        $this->redirect('/login');
    }
}
