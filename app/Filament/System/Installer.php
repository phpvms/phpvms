<?php

namespace App\Filament\System;

use App\Database\seeds\ShieldSeeder;
use App\Models\User;
use App\Services\AirlineService;
use App\Services\Installer\ConfigService;
use App\Services\Installer\DatabaseService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\RequirementsService;
use App\Services\Installer\SeederService;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\Utils;
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

    /**
     * Called whenever the component is loaded
     *
     * @return void
     */
    public function mount(): void
    {
        if (!empty(config('app.key')) && config('app.key') !== 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=' && Schema::hasTable('users') && User::count() > 0) {
            Notification::make()
                ->title('phpVMS is already installed')
                ->danger()
                ->send();

            $this->redirect('/admin');
            return;
        }

        // We want to run migrations if we are on the migrations page or if we're going to be moved there
        if (request()->get('step') === 'migrations' || (!request()->has('step') && !empty(config('app.key')) && config('app.key') !== 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')) {
            $this->dispatch('start-migrations');
        }

        $this->fillForm();
    }

    /**
     * To fill the form (set default values)
     *
     * @return void
     */
    public function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill();

        $this->callHook('afterFill');
    }


    /**
     * The filament form
     *
     * @param  Form  $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        $requirementsData = $this->getRequirementsData();

        return $form->schema([
            Wizard::make([
                Wizard\Step::make('Requirements')
                    ->schema([
                        ViewField::make('requirements')
                            ->view('filament.system.installer_requirements')
                            ->viewData($requirementsData),
                    ])
                    ->beforeValidation(function () use ($requirementsData) {
                        if (!$requirementsData['php']['passed'] || !$requirementsData['extensionsPassed'] || !$requirementsData['directoriesPassed']) {
                            Notification::make()
                                ->title('Requirements are not met')
                                ->danger()
                                ->send();

                            throw new Halt();
                        }
                    }),

                // Required only if we haven't passed this step yet (ie no .env yet)
                Wizard\Step::make('Database Setup')->schema([
                    Section::make('Site Config')
                        ->statePath('env')
                        ->columns()
                        ->schema([
                            TextInput::make('site_name')
                                ->label('Site Name')
                                ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
                                ->string(),

                            TextInput::make('app_url')
                                ->label('Site URL')
                                ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
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
                                ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
                                ->live()
                                ->options(['mysql' => 'mysql', 'mariadb' => 'mariadb', 'sqlite' => 'sqlite']),

                            TextInput::make('db_prefix')
                                ->string()
                                ->hint('Set this if you\'re sharing the database with another application')
                                ->label('Database Prefix'),

                            Group::make([
                                TextInput::make('db_host')
                                    ->label('Database Host')
                                    ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
                                    ->string()
                                    ->hintAction(
                                        Action::make('testDb')
                                            ->label('Test Database Credentials')
                                            ->action(fn () => $this->testDb())
                                    )
                                    ->default('localhost'),

                                TextInput::make('db_port')
                                    ->label('Database Port')
                                    ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
                                    ->numeric()
                                    ->default('3306'),

                                TextInput::make('db_name')
                                    ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
                                    ->string()
                                    ->label('Database Name'),

                                TextInput::make('db_user')
                                    ->required(empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
                                    ->string()
                                    ->label('Database User'),

                                TextInput::make('db_pass')
                                    ->password()
                                    ->revealable()
                                    ->label('Database Password'),
                            ])
                                ->visible(fn (Get $get): bool => $get('db_conn') && $get('db_conn') !== 'sqlite')
                                ->columns()
                                ->columnSpanFull(),
                        ]),
                ])->afterValidation(
                    function () {
                        $this->envAndDBSetup();
                    }
                ),

                Wizard\Step::make('Migrations')->schema([
                    ViewField::make('details')
                        ->view('filament.system.migrations_details'),
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
                                ->url('/system/legacy-import'),
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
                                ->required(),
                        ])->columns(),
                ]),
            ])
                ->startOnStep(function (): int {
                    // If .env hasn't been created yet, we want to create it
                    if (empty(config('app.key')) || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=') {
                        return 1;
                    }

                    // Else we directly skip to migrations
                    return 3;
                })
                ->persistStepInQueryString()
                ->submitAction(new HtmlString(Blade::render(
                    <<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Complete Setup
                    </x-filament::button>
                BLADE
                ))),
        ]);
    }

    /**
     * Retrieve phpvms' requirements
     *
     * @return array
     */
    private function getRequirementsData(): array
    {
        $reqSvc = app(RequirementsService::class);

        $php_version = $reqSvc->checkPHPVersion();
        $extensions = $reqSvc->checkExtensions();
        $directories = $reqSvc->checkPermissions();

        $extensionsPassed = $this->allPassed($extensions);
        $directoriesPassed = $this->allPassed($directories);

        return [
            'php'               => $php_version,
            'extensions'        => $extensions,
            'extensionsPassed'  => $extensionsPassed,
            'directories'       => $directories,
            'directoriesPassed' => $directoriesPassed,
        ];
    }

    /**
     * Check if all item of an array passed requirements
     *
     * @param  array  $arr
     * @return bool
     */
    private function allPassed(array $arr): bool
    {
        foreach ($arr as $item) {
            if ($item['passed'] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set up .env and trigger start migrations
     *
     * @return void
     * @throws Halt
     */
    private function envAndDbSetup(): void
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
            'SITE_NAME'     => $data['site_name'],
            'APP_URL'       => $data['app_url'],
            'DB_CONNECTION' => $data['db_conn'],
            'DB_HOST'       => $data['db_host'],
            'DB_PORT'       => $data['db_port'],
            'DB_DATABASE'   => $data['db_name'],
            'DB_USERNAME'   => $data['db_user'],
            'DB_PASSWORD'   => $data['db_pass'],
            'DB_PREFIX'     => $data['db_prefix'],
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

    /**
     * Run the migrations
     *
     * @return void
     */
    public function migrate(): void
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

    /**
     * Create first user and airline
     *
     * @return void
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    private function airlineAndUserSetup(): void
    {
        $data = $this->user ?? [];

        // Create the first airline
        $attrs = [
            'icao'    => $data['airline_icao'],
            'name'    => $data['airline_name'],
            'country' => $data['airline_country'],
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

    /**
     * Test db connection
     *
     * @return bool
     */
    private function testDb(): bool
    {
        $data = $this->env ?? [];

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

    /**
     * Called when the form is filed
     *
     * @return void
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function save(): void
    {
        $this->validate();
        $this->airlineAndUserSetup();

        flash()->success('phpVMS Installation Completed Successfully');
        $this->redirect('/login');
    }
}
