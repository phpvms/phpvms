<?php

namespace App\Filament\System;

use App\Filament\Infolists\Components\StreamEntry;
use App\Models\User;
use App\Services\AirlineService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\RequirementsService;
use App\Services\Installer\SeederService;
use App\Services\Installer\StreamedCommandsService;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\Utils;
use Database\Seeders\ShieldSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * @property-read FilamentSchema $form
 */
class Installer extends Page
{
    protected static ?string $slug = 'install';

    public string $stream = 'console_output';

    public ?array $user;

    /**
     * Called whenever the component is loaded
     */
    public function mount(): void
    {
        try {
            if (!empty(config('app.key')) && config('app.key') !== 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=' && Schema::hasTable('users') && User::count() > 0) {
                Notification::make()
                    ->title(__('installer.already_installed'))
                    ->danger()
                    ->send();

                $this->redirect('/admin');

                return;
            }
        } catch (QueryException $e) {

        }

        $this->form->fill();
    }

    public function content(FilamentSchema $schema): FilamentSchema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save'),
        ]);
    }

    /**
     * The filament form
     */
    public function form(FilamentSchema $schema): FilamentSchema
    {
        return $schema->components([
            Wizard::make([
                $this->getRequirementsStep(),

                $this->getMigrationStep(),

                $this->getUserAndAirlineSetupStep(),
            ])
                ->persistStepInQueryString()
                ->submitAction(new HtmlString(Blade::render(
                    <<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        {{ __('installer.complete_setup') }}
                    </x-filament::button>
                BLADE
                ))),
        ]);
    }

    /**
     * Retrieve phpvms' requirements
     */
    private function getRequirementsData(): array
    {

        $reqSvc = app(RequirementsService::class);

        $php_version = $reqSvc->checkPHPVersion();
        $extensions = $reqSvc->checkExtensions();
        $directories = $reqSvc->checkPermissions();

        $ext = [];
        foreach ($extensions as $extData) {
            $ext[$extData['ext']] = $extData['passed'] ? 'OK' : __('installer.failed');
        }

        $dirs = [];
        foreach ($directories as $extData) {
            $dirs[$extData['dir']] = $extData['passed'] ? 'OK' : __('installer.failed');
        }

        $extensionsPassed = $this->allPassed($extensions);
        $directoriesPassed = $this->allPassed($directories);

        try {
            DB::connection()->getPdo();
            $db = [
                'passed' => true,
                'msg'    => __('installer.db_connection_ok'),
            ];
        } catch (\Exception $e) {
            Log::error('Error while trying to connect to the database', [$e]);
            $db = [
                'passed' => false,
                'msg'    => __('installer.db_connection_failed', ['exception' => $e->getMessage()]),
            ];
        }

        return [
            'php'               => $php_version,
            'extensions'        => $ext,
            'extensionsPassed'  => $extensionsPassed,
            'directories'       => $dirs,
            'directoriesPassed' => $directoriesPassed,
            'db'                => $db,
        ];
    }

    /**
     * Check if all item of an array passed requirements
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

    public function migrate(): Action
    {
        return Action::make('migrate')
            ->label(__('installer.update'))
            ->action(function (Entry $component) {
                $output = __('installer.starting_migration_process').PHP_EOL;
                $this->stream(to: $this->stream, content: PHP_EOL.__('installer.starting_migration_process').PHP_EOL);

                if (function_exists('proc_open')) {
                    // Streaming the output of the command is only available with proc_open (relies on Symfony Process)
                    app(MigrationService::class)
                        ->runAllMigrationsWithStreaming(function (string $buffer) use (&$output) {
                            $output .= $buffer;
                            $this->stream(to: $this->stream, content: $buffer);
                        });
                } else {
                    $output .= app(MigrationService::class)
                        ->runAllMigrations();

                    $this->stream(to: $this->stream, content: $output);
                }

                app(SeederService::class)->syncAllSeeds();

                if (function_exists('proc_open')) {
                    app(StreamedCommandsService::class)->streamArtisanCommand(['db:seed', '--force', '--class='.ShieldSeeder::class], function (string $buffer) use (&$output) {
                        $output .= $buffer;
                        $this->stream(to: $this->stream, content: $buffer);
                    });
                } else {
                    Artisan::call('db:seed', ['--force' => true, '--class' => ShieldSeeder::class]);
                    $buffer = Artisan::output();
                    $output .= $buffer;
                    $this->stream(to: $this->stream, content: $buffer);
                }

                $output .= __('installer.migrations_completed').PHP_EOL;
                $this->stream($this->stream, __('installer.migrations_completed').PHP_EOL);

                // Let's generate a new key if the app is still using the one from the .env.example
                if (config('app.key') === 'base64:1IcdcyMVAztKFFiqfJOX5w6FkOb9ONnjCA3bdxNbtQ4=' || config('app.key') === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=') {
                    $output .= __('installer.app_key_warning').' php artisan key:generate --force'.PHP_EOL;
                    $this->stream(to: $this->stream, content: __('installer.app_key_warning').' php artisan key:generate --force'.PHP_EOL);
                }

                $component->state(fn () => $output);

                return true;
            });
    }

    /**
     * Create first user and airline
     *
     * @throws ValidatorException
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
     * Called when the form is filed
     *
     * @throws ValidatorException
     */
    public function save(): void
    {
        $this->validate();
        $this->airlineAndUserSetup();

        flash()->success(__('installer.install_completed'));
        $this->redirect('/login');
    }

    private function getRequirementsStep(): Step
    {
        $data = $this->getRequirementsData();

        return
            Step::make(__('installer.requirements'))
                ->schema([
                    TextEntry::make('info')
                        ->label(__('installer.important'))
                        ->hintAction(Action::make('openDocs')
                            ->label(__('common.see_the_docs'))
                            ->url(docs_link('installation'))
                            ->openUrlInNewTab()
                        )
                        ->state(fn () => __('installer.create_env')),

                    Section::make('PHP')
                        ->afterHeader([
                            TextEntry::make('php_passed')
                                ->hiddenLabel()
                                ->size('md')
                                ->state(fn () => $data['php']['passed'] && $data['extensionsPassed'] ? 'OK' : __('installer.failed'))
                                ->color(fn () => $data['php']['passed'] && $data['extensionsPassed'] ? 'success' : 'danger')
                                ->badge(),
                        ])
                        ->schema([
                            TextEntry::make('php_version')
                                ->inlineLabel()
                                ->label(__('installer.php_version'))
                                ->alignEnd()
                                ->badge()
                                ->state(fn () => $data['php']['version']),

                            KeyValueEntry::make('extensions')
                                ->label(__('installer.php_extensions'))
                                ->keyLabel(__('installer.extension'))
                                ->valueLabel(__('common.status'))
                                ->state(fn () => $data['extensions']),
                        ])
                        ->columnSpanFull(),

                    Section::make(__('installer.directory_permissions'))
                        ->afterHeader([
                            TextEntry::make('directory_passed')
                                ->hiddenLabel()
                                ->size('md')
                                ->state(fn () => $data['directoriesPassed'] ? 'OK' : __('installer.failed'))
                                ->color(fn () => $data['directoriesPassed'] ? 'success' : 'danger')
                                ->badge(),
                        ])
                        ->description(__('installer.directory_permissions_description'))
                        ->schema([
                            KeyValueEntry::make('directories')
                                ->hiddenLabel()
                                ->keyLabel(__('installer.directory'))
                                ->valueLabel(__('common.status'))
                                ->state(fn () => $data['directories']),
                        ])
                        ->columnSpanFull(),

                    Section::make(__('installer.database'))
                        ->afterHeader([
                            TextEntry::make('database_passed')
                                ->hiddenLabel()
                                ->size('md')
                                ->state(fn () => $data['db']['passed'] ? 'OK' : __('installer.failed'))
                                ->color(fn () => $data['db']['passed'] ? 'success' : 'danger')
                                ->badge(),
                        ])
                        ->schema([
                            TextEntry::make('db_connection')
                                ->inlineLabel($data['db']['passed'])
                                ->label(__('installer.database_connection'))
                                ->color(fn () => $data['db']['passed'] ? 'success' : 'danger')
                                ->alignEnd($data['db']['passed'])
                                ->badge($data['db']['passed'])
                                ->state(fn () => $data['db']['msg']),
                        ]),
                ])
                ->beforeValidation(function () use ($data) {
                    if (!$data['php']['passed'] || !$data['extensionsPassed'] || !$data['directoriesPassed'] || !$data['db']['passed']) {
                        Notification::make()
                            ->title(__('installer.requirements_not_met'))
                            ->danger()
                            ->send();

                        throw new Halt();
                    }
                });

    }

    private function getMigrationStep(): Step
    {
        return Step::make(__('installer.migrations'))
            ->schema([
                StreamEntry::make('output')
                    ->state(fn () => __('installer.click_update_to_run'))
                    ->afterLabel($this->migrate())
                    ->label(__('installer.output'))
                    ->viewData([
                        'stream' => $this->stream,
                    ]),
            ])
            ->afterValidation(function () {
                if (count(app(MigrationService::class)->migrationsAvailable()) > 0) {
                    Notification::make()
                        ->title(__('installer.migrations_not_completed', ['count' => count(app(MigrationService::class)->migrationsAvailable())]))
                        ->danger()
                        ->send();

                    throw new Halt();
                }
            });
    }

    private function getUserAndAirlineSetupStep(): Step
    {
        return
            Step::make(__('installer.user_and_airline_setup'))
                ->schema([
                    Section::make(__('filament.airline_informations'))
                        ->statePath('user')
                        ->headerActions([
                            Action::make('test')
                                ->label(__('installer.legacy_importer'))
                                ->openUrlInNewTab()
                                ->url(docs_link('importing_legacy')),
                        ])
                        ->schema([
                            TextInput::make('airline_icao')
                                ->length(3)
                                ->string()
                                ->required()
                                ->unique('airlines', 'icao')
                                ->label('ICAO'),

                            TextInput::make('airline_name')
                                ->string()
                                ->required()
                                ->label(__('common.name')),

                            Select::make('airline_country')
                                ->label(__('common.country'))
                                ->options(Countries::getSelectList())
                                ->native(false)
                                ->required()
                                ->searchable(),
                        ])
                        ->columns(),

                    Section::make(__('installer.super_admin_informations'))
                        ->statePath('user')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('common.name'))
                                ->required()
                                ->string(),

                            TextInput::make('email')
                                ->label(__('common.email'))
                                ->unique('users', 'email')
                                ->required()
                                ->email(),

                            TextInput::make('password')
                                ->label(__('auth.password'))
                                ->revealable()
                                ->password()
                                ->confirmed()
                                ->required(),

                            TextInput::make('password_confirmation')
                                ->label(__('passwords.confirm'))
                                ->password()
                                ->required(),
                        ])
                        ->columns(),
                ]);
    }

    public function getTitle(): string
    {
        return __('installer.title');
    }
}
