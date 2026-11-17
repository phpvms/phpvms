<?php

declare(strict_types=1);

use App\Settings\AirportsSettings;
use App\Settings\BidsSettings;
use App\Settings\BrandingSettings;
use App\Settings\CaptchaSettings;
use App\Settings\CronSettings;
use App\Settings\FaresSettings;
use App\Settings\FlightsSettings;
use App\Settings\GeneralSettings;
use App\Settings\LivemapSettings;
use App\Settings\MapSettings;
use App\Settings\NotificationsSettings;
use App\Settings\OperationsSettings;
use App\Settings\PilotsSettings;
use App\Settings\PirepsSettings;
use App\Settings\RegistrySettings;
use App\Settings\SimbriefSettings;
use App\Settings\ToursSettings;
use App\Settings\UnitsSettings;
use Spatie\LaravelData\Data;
use Spatie\LaravelSettings\SettingsCasts\DataCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;
use Spatie\LaravelSettings\SettingsRepositories\RedisSettingsRepository;

return [

    /*
     * The core settings classes, one per settings group. Listed explicitly
     * (auto-discovery is off below), so `settings:discover` / `optimize`
     * produce the same list and nothing scans the filesystem at boot. Addon
     * settings classes are registered by App\Features\Addons\Support\AddonSettingsRegistry.
     */
    'settings' => [
        GeneralSettings::class,
        CaptchaSettings::class,
        UnitsSettings::class,
        LivemapSettings::class,
        MapSettings::class,
        AirportsSettings::class,
        BidsSettings::class,
        ToursSettings::class,
        FlightsSettings::class,
        OperationsSettings::class,
        SimbriefSettings::class,
        PirepsSettings::class,
        PilotsSettings::class,
        NotificationsSettings::class,
        FaresSettings::class,
        CronSettings::class,
        RegistrySettings::class,
        BrandingSettings::class,
    ],

    /*
     * The path where the settings classes will be created.
     */
    'setting_class_path' => app_path('Settings'),

    /*
     * Settings migrations in these directories run with the schema migrations.
     * phpVMS keeps it empty: a change to setting values is a data migration in
     * `database/migrations_data/` (a SettingsMigration works there too), so it
     * runs after the settings were copied from the backup tables. The
     * directory exists only because the package scans it.
     */
    'migrations_paths' => [
        database_path('settings'),
    ],

    /*
     * When no repository was set for a settings class the following repository
     * will be used for loading and saving settings.
     */
    'default_repository' => 'database',

    /*
     * Settings will be stored and loaded from these repositories.
     */
    'repositories' => [
        'database' => [
            'type'       => DatabaseSettingsRepository::class,
            'model'      => null,
            'table'      => 'settings',
            'connection' => null,
        ],

        /*
         * Addon settings: every App\Contracts\Addons\AddonSettings class.
         */
        'addons' => [
            'type'       => DatabaseSettingsRepository::class,
            'model'      => null,
            'table'      => 'addon_settings',
            'connection' => null,
        ],
        'redis' => [
            'type'       => RedisSettingsRepository::class,
            'connection' => null,
            'prefix'     => null,
        ],
    ],

    /*
     * The encoder and decoder will determine how settings are stored and
     * retrieved in the database. By default, `json_encode` and `json_decode`
     * are used.
     */
    'encoder' => null,
    'decoder' => null,

    /*
     * The contents of settings classes can be cached through your application,
     * settings will be stored within a provided Laravel store and can have an
     * additional prefix.
     */
    'cache' => [
        'enabled' => (bool) env('SETTINGS_CACHE_ENABLED', true),
        'store'   => env('SETTINGS_CACHE_STORE'),
        'prefix'  => null, // the installed version is appended at run time, see AppServiceProvider
        'ttl'     => null,

        /*
         * When enabled, uses Laravel's memoized cache driver (requires Laravel 12.9+)
         * to keep resolved values in memory during a single request.
         */
        'memo' => (bool) env('SETTINGS_CACHE_MEMO', true),
    ],

    /*
     * These global casts will be automatically used whenever a property within
     * your settings class isn't a default PHP type.
     */
    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class      => DateTimeZoneCast::class,
        //        Spatie\DataTransferObject\DataTransferObject::class => Spatie\LaravelSettings\SettingsCasts\DtoCast::class,
        Data::class => DataCast::class,
    ],

    /*
     * The package will look for settings in these paths and automatically
     * register them.
     */
    'auto_discover_settings' => [],

    /*
     * Automatically discovered settings classes can be cached, so they don't
     * need to be searched each time the application boots up.
     */
    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
