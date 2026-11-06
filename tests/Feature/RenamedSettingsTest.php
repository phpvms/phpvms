<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\SettingService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * Settings moved to another key stay reachable through their old key, and the
 * data migration moves existing rows without losing the operator's value.
 */
function airportSettingsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_05_000000_move_airport_settings_to_airports_group.php');
}

/** Put the DB in the pre-upgrade shape: only the old rows exist. */
function seedOldAirportRows(string $lookup, string $unadded): void
{
    Setting::whereIn('id', ['airports_auto_airport_lookup', 'airports_allow_unadded_airports'])->delete();
    Setting::where('id', 'general_auto_airport_lookup')->delete();
    Setting::where('id', 'general_allow_unadded_airports')->delete();

    foreach ([
        'general.auto_airport_lookup'    => $lookup,
        'general.allow_unadded_airports' => $unadded,
    ] as $key => $value) {
        $row = new Setting([
            'name'  => $key,
            'key'   => $key,
            'value' => $value,
            'group' => 'general',
            'type'  => 'boolean',
        ]);
        $row->id = Setting::formatKey($key);
        $row->save();
    }

    app(SettingService::class)->clearMemo();
}

it('reads the new key through the old key', function (): void {
    app(SettingService::class)->store('airports.auto_airport_lookup', false);
    app(SettingService::class)->clearMemo();

    expect(setting('general.auto_airport_lookup'))->toBeFalsy();

    app(SettingService::class)->store('airports.auto_airport_lookup', true);

    expect(setting('general.auto_airport_lookup'))->toBeTruthy();
});

it('writes through the old key into the new row', function (): void {
    setting_save('general.allow_unadded_airports', true);

    expect(Setting::find('airports_allow_unadded_airports')->value)->toBe('1')
        ->and(setting('airports.allow_unadded_airports'))->toBeTruthy()
        ->and(setting('general.allow_unadded_airports'))->toBeTruthy();

    app(SettingService::class)->store('general.allow_unadded_airports', false);

    expect(setting('airports.allow_unadded_airports'))->toBeFalsy()
        ->and(setting('general.allow_unadded_airports'))->toBeFalsy();
});

it('shares one cache slot between both spellings in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $service = app(SettingService::class);

    // Prime the cache through the old key, then write through the old key.
    setting('general.auto_airport_lookup');
    $service->store('general.auto_airport_lookup', false);

    expect(setting('airports.auto_airport_lookup'))->toBeFalsy()
        ->and(setting('general.auto_airport_lookup'))->toBeFalsy();

    // A fresh request (empty memo) must read the new value from the cache,
    // not a stale entry under either key.
    $service->clearMemo();
    $service->store('airports.auto_airport_lookup', true);
    $service->clearMemo();

    expect(Cache::has(config('cache.keys.SETTINGS.key').'general.auto_airport_lookup'))->toBeFalse()
        ->and(setting('general.auto_airport_lookup'))->toBeTruthy()
        ->and(setting('airports.auto_airport_lookup'))->toBeTruthy();
});

it('renames the old rows in place and keeps their values', function (): void {
    seedOldAirportRows('0', '1');

    airportSettingsMigration()->up();

    $lookup = Setting::find('airports_auto_airport_lookup');
    $unadded = Setting::find('airports_allow_unadded_airports');

    expect($lookup->value)->toBe('0')
        ->and($lookup->key)->toBe('airports.auto_airport_lookup')
        ->and($lookup->group)->toBe('airports')
        ->and($unadded->value)->toBe('1')
        ->and($unadded->group)->toBe('airports')
        ->and(Setting::where('id', 'like', 'general_%airport%')->count())->toBe(0);
});

it('copies the old value onto a seeded new row and removes the old row', function (): void {
    seedOldAirportRows('0', '1');
    $this->seed(SettingsSeeder::class);

    // The seeder ran first: new rows hold their defaults, old rows still exist.
    expect(Setting::find('airports_auto_airport_lookup')->value)->toBe('true')
        ->and(Setting::find('general_auto_airport_lookup'))->not->toBeNull();

    airportSettingsMigration()->up();

    expect(Setting::find('airports_auto_airport_lookup')->value)->toBe('0')
        ->and(Setting::find('airports_allow_unadded_airports')->value)->toBe('1')
        ->and(Setting::find('general_auto_airport_lookup'))->toBeNull()
        ->and(Setting::find('general_allow_unadded_airports'))->toBeNull();
});

it('does nothing when only the new rows exist', function (): void {
    app(SettingService::class)->store('airports.auto_airport_lookup', false);
    $before = Setting::find('airports_auto_airport_lookup')->value;

    airportSettingsMigration()->up();

    expect(Setting::find('airports_auto_airport_lookup')->value)->toBe($before);
});
