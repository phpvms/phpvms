<?php

declare(strict_types=1);

use App\Models\LegacySetting;
use App\Services\SettingService;
use App\Support\Settings\LegacySettingsCopier;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;

/**
 * Settings moved to another key stay reachable through their old key, and the
 * data migration moves existing rows without losing the operator's value.
 *
 * The data migrations run against `settings_backup`, the settings table as it
 * was before typed settings; the helpers read the current settings tables.
 */

/** The stored, decoded payload of a current setting row, or null. */
function storedPayload(string $key): mixed
{
    [$group, $name] = explode('.', $key, 2);
    $payload = DB::table('settings')->where('group', $group)->where('name', $name)->value('payload');

    return $payload === null ? null : json_decode((string) $payload, true);
}

function airportSettingsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_05_000000_move_airport_settings_to_airports_group.php');
}

/** Put the DB in the pre-upgrade shape: only the old rows exist. */
function seedOldAirportRows(string $lookup, string $unadded): void
{
    LegacySetting::whereIn('id', ['airports_auto_airport_lookup', 'airports_allow_unadded_airports'])->delete();
    LegacySetting::where('id', 'general_auto_airport_lookup')->delete();
    LegacySetting::where('id', 'general_allow_unadded_airports')->delete();

    foreach ([
        'general.auto_airport_lookup'    => $lookup,
        'general.allow_unadded_airports' => $unadded,
    ] as $key => $value) {
        $row = new LegacySetting([
            'name'  => $key,
            'key'   => $key,
            'value' => $value,
            'group' => 'general',
            'type'  => 'boolean',
        ]);
        $row->id = LegacySetting::formatKey($key);
        $row->save();
    }

}

it('reads the new key through the old key', function (): void {
    app(SettingService::class)->store('airports.auto_airport_lookup', false);

    expect(setting('general.auto_airport_lookup'))->toBeFalsy();

    app(SettingService::class)->store('airports.auto_airport_lookup', true);

    expect(setting('general.auto_airport_lookup'))->toBeTruthy();
});

it('writes through the old key into the new row', function (): void {
    setting_save('general.allow_unadded_airports', true);

    expect(storedPayload('airports.allow_unadded_airports'))->toBeTrue()
        ->and(setting('airports.allow_unadded_airports'))->toBeTruthy()
        ->and(setting('general.allow_unadded_airports'))->toBeTruthy();

    app(SettingService::class)->store('general.allow_unadded_airports', false);

    expect(setting('airports.allow_unadded_airports'))->toBeFalsy()
        ->and(setting('general.allow_unadded_airports'))->toBeFalsy();
});

it('reads one value through both spellings across requests with the cache on', function (): void {
    enableSettingsCache();

    // Load through the old key, then write through the old key.
    setting('general.auto_airport_lookup');
    setting_save('general.auto_airport_lookup', false);

    expect(setting('airports.auto_airport_lookup'))->toBeFalsy()
        ->and(setting('general.auto_airport_lookup'))->toBeFalsy();

    // A fresh request reads the cached group, which the write replaced.
    app()->forgetScopedInstances();
    setting_save('airports.auto_airport_lookup', true);
    app()->forgetScopedInstances();

    expect(setting('general.auto_airport_lookup'))->toBeTruthy()
        ->and(setting('airports.auto_airport_lookup'))->toBeTruthy();
});

it('renames the old rows in place and keeps their values', function (): void {
    seedOldAirportRows('0', '1');

    airportSettingsMigration()->up();

    $lookup = LegacySetting::find('airports_auto_airport_lookup');
    $unadded = LegacySetting::find('airports_allow_unadded_airports');

    expect($lookup->value)->toBe('0')
        ->and($lookup->key)->toBe('airports.auto_airport_lookup')
        ->and($lookup->group)->toBe('airports')
        ->and($unadded->value)->toBe('1')
        ->and($unadded->group)->toBe('airports')
        ->and(LegacySetting::where('id', 'like', 'general_%airport%')->count())->toBe(0);
});

it('copies the old value onto a seeded new row and removes the old row', function (): void {
    seedOldAirportRows('0', '1');
    seedLegacySettings();

    // The seeder ran first: new rows hold their defaults, old rows still exist.
    expect(LegacySetting::find('airports_auto_airport_lookup')->value)->toBe('true')
        ->and(LegacySetting::find('general_auto_airport_lookup'))->not->toBeNull();

    airportSettingsMigration()->up();

    expect(LegacySetting::find('airports_auto_airport_lookup')->value)->toBe('0')
        ->and(LegacySetting::find('airports_allow_unadded_airports')->value)->toBe('1')
        ->and(LegacySetting::find('general_auto_airport_lookup'))->toBeNull()
        ->and(LegacySetting::find('general_allow_unadded_airports'))->toBeNull();
});

it('does nothing when only the new rows exist', function (): void {
    LegacySetting::whereIn('id', ['general_auto_airport_lookup', 'airports_auto_airport_lookup'])->delete();
    insertOldSetting('airports.auto_airport_lookup', '0');
    $before = LegacySetting::find('airports_auto_airport_lookup')->value;

    airportSettingsMigration()->up();

    expect(LegacySetting::find('airports_auto_airport_lookup')->value)->toBe($before);
});

/** @var array<string, string> old key => new key, for the one-to-one operations moves */
const OPERATION_MOVES = [
    'pireps.restrict_aircraft_to_rank'       => 'operations.restrict_aircraft_to_rank',
    'pireps.restrict_aircraft_to_typerating' => 'operations.restrict_aircraft_to_typerating',
    'pireps.only_aircraft_at_dpt_airport'    => 'operations.only_aircraft_at_dpt_airport',
    'pireps.advanced_fuel'                   => 'operations.advanced_fuel',
    'flights.only_company_aircraft'          => 'operations.only_company_aircraft',
];

function operationSettingsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_07_000000_move_operation_settings_to_operations_group.php');
}

/** Insert an old-shape settings row. */
function insertOldSetting(string $key, string $value): void
{
    $row = new LegacySetting([
        'name'  => $key,
        'key'   => $key,
        'value' => $value,
        'group' => explode('.', $key)[0],
        'type'  => 'boolean',
    ]);
    $row->id = LegacySetting::formatKey($key);
    $row->save();
}

/**
 * Put the DB in the pre-upgrade shape: only the old rows exist. $locks holds the
 * stored value for each lock row; a null entry leaves that row missing.
 *
 * @param array{bids: ?string, simbrief: ?string} $locks
 */
function seedOldOperationRows(string $value, array $locks = ['bids' => '1', 'simbrief' => '1']): void
{
    foreach (array_keys(OPERATION_MOVES) as $old) {
        LegacySetting::where('id', LegacySetting::formatKey($old))->delete();
        LegacySetting::where('id', LegacySetting::formatKey(OPERATION_MOVES[$old]))->delete();
        insertOldSetting($old, $value);
    }

    LegacySetting::whereIn('id', ['operations_block_aircraft', 'bids_block_aircraft', 'simbrief_block_aircraft'])->delete();
    foreach ($locks as $group => $lock) {
        if ($lock !== null) {
            insertOldSetting($group.'.block_aircraft', $lock);
        }
    }

}

it('reads each moved operations setting through its old key', function (): void {
    foreach (OPERATION_MOVES as $old => $new) {
        app(SettingService::class)->store($new, true);
        expect(setting($old))->toBeTruthy();

        app(SettingService::class)->store($new, false);
        expect(setting($old))->toBeFalsy();
    }
});

it('reads the merged lock through both old block_aircraft keys', function (): void {
    app(SettingService::class)->store('operations.block_aircraft', true);

    expect(setting('bids.block_aircraft'))->toBeTruthy()
        ->and(setting('simbrief.block_aircraft'))->toBeTruthy();

    app(SettingService::class)->store('operations.block_aircraft', false);

    expect(setting('bids.block_aircraft'))->toBeFalsy()
        ->and(setting('simbrief.block_aircraft'))->toBeFalsy();
});

it('writes through either old block_aircraft key into the merged row', function (): void {
    setting_save('bids.block_aircraft', false);
    expect(storedPayload('operations.block_aircraft'))->toBeFalse()
        ->and(setting('simbrief.block_aircraft'))->toBeFalsy();

    setting_save('simbrief.block_aircraft', true);
    expect(storedPayload('operations.block_aircraft'))->toBeTrue()
        ->and(setting('bids.block_aircraft'))->toBeTruthy();
});

it('renames the old operations rows in place and keeps their values', function (): void {
    seedOldOperationRows('1');

    operationSettingsMigration()->up();

    foreach (OPERATION_MOVES as $old => $new) {
        $row = LegacySetting::find(LegacySetting::formatKey($new));

        expect($row->value)->toBe('1')
            ->and($row->key)->toBe($new)
            ->and($row->group)->toBe('operations')
            ->and(LegacySetting::find(LegacySetting::formatKey($old)))->toBeNull();
    }
});

it('copies old operations values onto seeded new rows and removes the old rows', function (): void {
    seedOldOperationRows('1', ['bids' => '0', 'simbrief' => '1']);
    seedLegacySettings();

    // The seeder ran first: the new rows hold their defaults, the old rows still exist.
    expect(LegacySetting::find('operations_restrict_aircraft_to_rank')->value)->toBe('false')
        ->and(LegacySetting::find('pireps_restrict_aircraft_to_rank'))->not->toBeNull()
        ->and(LegacySetting::find('operations_block_aircraft')->value)->toBe('false');

    operationSettingsMigration()->up();

    foreach (OPERATION_MOVES as $old => $new) {
        expect(LegacySetting::find(LegacySetting::formatKey($new))->value)->toBe('1')
            ->and(LegacySetting::find(LegacySetting::formatKey($old)))->toBeNull();
    }

    expect(LegacySetting::find('operations_block_aircraft')->value)->toBe('1')
        ->and(LegacySetting::find('bids_block_aircraft'))->toBeNull()
        ->and(LegacySetting::find('simbrief_block_aircraft'))->toBeNull();
});

it('merges the two block_aircraft rows as an OR of their stored values', function (
    ?string $bids,
    ?string $simbrief,
    string $expected,
): void {
    seedOldOperationRows('0', ['bids' => $bids, 'simbrief' => $simbrief]);

    operationSettingsMigration()->up();

    $merged = LegacySetting::find('operations_block_aircraft');

    expect($merged->value)->toBe($expected)
        ->and($merged->key)->toBe('operations.block_aircraft')
        ->and($merged->group)->toBe('operations')
        ->and(LegacySetting::find('bids_block_aircraft'))->toBeNull()
        ->and(LegacySetting::find('simbrief_block_aircraft'))->toBeNull();
})->with([
    'bids on, simbrief off'          => ['1', '0', '1'],
    'bids off, simbrief on'          => ['0', '1', '1'],
    'both on'                        => ['1', '1', '1'],
    'both off'                       => ['0', '0', '0'],
    'bids missing counts as on'      => [null, '0', '1'],
    'simbrief missing counts as off' => ['0', null, '0'],
    'simbrief on, bids missing'      => [null, '1', '1'],
    'seeded true / false'            => ['true', 'false', '1'],
    'seeded false / true'            => ['false', 'true', '1'],
    'seeded false / false'           => ['false', 'false', '0'],
    'empty bids, false simbrief'     => ['', 'false', '0'],
    'false bids, empty simbrief'     => ['false', '', '0'],
]);

it('rolls the lock merge back when it fails part-way', function (): void {
    seedOldOperationRows('0', ['bids' => '0', 'simbrief' => '0']);
    // The merged row already exists and holds a value the merge would change.
    insertOldSetting('operations.block_aircraft', '1');

    // Fail the simbrief delete, the last statement of the merge.
    DB::listen(function ($query): void {
        if (str_starts_with($query->sql, 'delete from') && in_array('simbrief_block_aircraft', $query->bindings, true)) {
            throw new RuntimeException('interrupted');
        }
    });

    expect(function (): void {
        operationSettingsMigration()->up();
    })->toThrow(RuntimeException::class, 'interrupted');

    expect(LegacySetting::find('operations_block_aircraft')->value)->toBe('1')
        ->and(LegacySetting::find('bids_block_aircraft'))->not->toBeNull()
        ->and(LegacySetting::find('bids_block_aircraft')->value)->toBe('0')
        ->and(LegacySetting::find('simbrief_block_aircraft'))->not->toBeNull();
});

it('serves the merged lock, not a cached value, once the data migrations have run', function (): void {
    enableSettingsCache();
    updateSetting('operations.block_aircraft', false);
    seedOldOperationRows('0', ['bids' => '1', 'simbrief' => '0']);

    operationSettingsMigration()->up();
    app(LegacySettingsCopier::class)->copy();
    event(new MigrationsEnded('up'));
    app()->forgetScopedInstances();

    expect(setting('operations.block_aircraft'))->toBeTrue();
});

it('runs the operations migration twice without changing anything', function (): void {
    seedOldOperationRows('1', ['bids' => '0', 'simbrief' => '1']);

    operationSettingsMigration()->up();
    operationSettingsMigration()->up();

    foreach (OPERATION_MOVES as $new) {
        expect(LegacySetting::find(LegacySetting::formatKey($new))->value)->toBe('1');
    }

    expect(LegacySetting::find('operations_block_aircraft')->value)->toBe('1');
});

it('does nothing to the operations rows when only the new rows exist', function (): void {
    insertOldSetting('operations.advanced_fuel', '1');
    insertOldSetting('operations.block_aircraft', '0');

    $fuel = LegacySetting::find('operations_advanced_fuel')->value;

    operationSettingsMigration()->up();

    expect(LegacySetting::find('operations_advanced_fuel')->value)->toBe($fuel)
        ->and(LegacySetting::find('operations_block_aircraft')->value)->toBe('0');
});

/** @var array<string, string> old key => new key, for the pilot location moves */
const PILOT_LOCATION_MOVES = [
    'pilots.home_hubs_only'                 => 'operations.home_hubs_only',
    'pilots.only_flights_from_current'      => 'operations.only_flights_from_current',
    'pilots.only_show_flights_from_current' => 'operations.only_show_flights_from_current',
];

function pilotLocationSettingsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_07_000001_move_pilot_location_settings_to_operations_group.php');
}

/** Put the DB in the pre-upgrade shape: only the old rows exist. */
function seedOldPilotLocationRows(string $value): void
{
    foreach (PILOT_LOCATION_MOVES as $old => $new) {
        LegacySetting::whereIn('id', [LegacySetting::formatKey($old), LegacySetting::formatKey($new)])->delete();
        insertOldSetting($old, $value);
    }

}

it('reads each moved pilot location setting through its old key', function (): void {
    foreach (PILOT_LOCATION_MOVES as $old => $new) {
        app(SettingService::class)->store($new, true);
        expect(setting($old))->toBeTruthy();

        setting_save($old, false);
        expect(storedPayload($new))->toBeFalse();
    }
});

it('renames the old pilot location rows in place and keeps their values', function (): void {
    seedOldPilotLocationRows('1');

    pilotLocationSettingsMigration()->up();

    foreach (PILOT_LOCATION_MOVES as $old => $new) {
        $row = LegacySetting::find(LegacySetting::formatKey($new));

        expect($row->value)->toBe('1')
            ->and($row->key)->toBe($new)
            ->and($row->group)->toBe('operations')
            ->and(LegacySetting::find(LegacySetting::formatKey($old)))->toBeNull();
    }
});

it('copies old pilot location values onto seeded new rows and removes the old rows', function (): void {
    seedOldPilotLocationRows('1');
    seedLegacySettings();

    // The seeder ran first: the new rows hold their defaults, the old rows still exist.
    expect(LegacySetting::find('operations_home_hubs_only')->value)->toBe('false')
        ->and(LegacySetting::find('pilots_home_hubs_only'))->not->toBeNull();

    pilotLocationSettingsMigration()->up();

    foreach (PILOT_LOCATION_MOVES as $old => $new) {
        expect(LegacySetting::find(LegacySetting::formatKey($new))->value)->toBe('1')
            ->and(LegacySetting::find(LegacySetting::formatKey($old)))->toBeNull();
    }
});

it('does nothing to the pilot location rows when only the new rows exist', function (): void {
    insertOldSetting('operations.only_flights_from_current', '1');

    pilotLocationSettingsMigration()->up();
    pilotLocationSettingsMigration()->up();

    expect(LegacySetting::find('operations_only_flights_from_current')->value)->toBe('1');
});
