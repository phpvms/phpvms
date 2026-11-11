<?php

use App\Models\LegacySetting;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\UserService;
use App\Services\YamlDatabaseService;
use App\Support\Settings\LegacySettingsCopier;
use App\Support\Settings\SettingsCacheRefresher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\LaravelSettings\SettingsEventSubscriber;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

/**
 * Strip identifier quoting out of generated SQL.
 *
 * An assertion about the *shape* of a compiled query has to read the same on
 * every driver, and the grammars disagree on quoting: MySQL emits backticks
 * where PostgreSQL and SQLite emit double quotes. Bindings are always `?`, so
 * there are no string literals here for this to damage.
 */
function unquoteSql(string $sql): string
{
    return str_replace(['`', '"'], '', $sql);
}

/**
 * Load the given yaml file into the database
 */
function loadYamlIntoDb(string $file): void
{
    $file_path = base_path('tests/data/'.$file.'.yml');
    app(YamlDatabaseService::class)->seedFromYamlFile($file_path);
}

/**
 * Update the given setting key with the given value
 */
function updateSetting(string $key, $value): void
{
    setting_save($key, $value);
}

/**
 * Turn the settings cache on (the suite runs with it off). The package reads
 * the cache config when it binds the settings classes and when it subscribes
 * its cache-writing listener, both at boot, so both are done again; the boot
 * listener stays subscribed but writes nothing with the cache off.
 */
function enableSettingsCache(string $store = 'array'): void
{
    config(['settings.cache.enabled' => true, 'settings.cache.store' => $store]);

    app(SettingsContainer::class)->registerBindings();
    Event::subscribe(new SettingsEventSubscriber(new SettingsCacheFactory(config('settings'))));
    app()->forgetScopedInstances();
}

/**
 * Reload every settings class from its table, as the next request would after
 * a write that bypassed the settings classes.
 */
function reloadSettings(): void
{
    app(SettingsCacheRefresher::class)->refreshAll();
}

/**
 * Fill `settings_backup` the way SettingsSeeder filled the settings table
 * before typed settings: the pre-upgrade state the historical data migrations
 * run against. Existing rows keep their value.
 */
function seedLegacySettings(): void
{
    $positions = [];
    $rows = [];

    foreach (require base_path('tests/data/legacy_settings.php') as $setting) {
        $position = $positions[$setting['group']] = ($positions[$setting['group']] ?? -1) + 1;

        $rows[] = [
            'id'          => LegacySetting::formatKey($setting['key']),
            'key'         => $setting['key'],
            'name'        => $setting['name'],
            'value'       => $setting['value'],
            'default'     => $setting['value'],
            'group'       => $setting['group'],
            'offset'      => $position,
            'order'       => $position,
            'type'        => $setting['type'],
            'options'     => $setting['options'],
            'description' => $setting['description'],
        ];
    }

    LegacySetting::upsert($rows, uniqueBy: ['id'], update: ['key', 'name', 'group', 'offset', 'order', 'type', 'options', 'description', 'default']);
}

/**
 * Copy the backup tables into the settings tables, as the last data migration
 * does on an upgrade, and reload the settings.
 */
function copyLegacySettings(): void
{
    app(LegacySettingsCopier::class)->copy();
    reloadSettings();
}

/**
 * Write a setting's stored payload directly, bypassing its settings class
 * (casting, validation and events), then reload the settings.
 */
function writeRawSetting(string $key, mixed $payload, string $table = 'settings'): void
{
    [$group, $name] = explode('.', $key, 2);

    DB::table($table)->updateOrInsert(
        ['group' => $group, 'name' => $name],
        ['payload' => json_encode($payload), 'locked' => false],
    );

    reloadSettings();
}

/**
 * Create an admin user
 */
function createAdminUser(array $attrs = []): User
{
    $admin = User::factory()->create($attrs);

    $userSvc = app(UserService::class);
    $userSvc->addUserToRole($admin, 'super_admin');

    return $admin;
}

/**
 * Create a new PIREP with a proper subfleet/rank/user and an
 * aircraft that the user is allowed to fly
 */
function createPirep(array $user_attrs = [], array $pirep_attrs = []): Pirep
{
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $user = User::factory()->create(array_merge([
        'rank_id' => $rank->id,
    ], $user_attrs));

    // Return a Pirep model
    return Pirep::factory()->make(array_merge([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random()->id,
    ], $pirep_attrs));
}
