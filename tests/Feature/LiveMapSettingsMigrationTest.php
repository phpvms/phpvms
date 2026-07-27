<?php

declare(strict_types=1);

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

function liveMapSettingsMigration(): object
{
    return require base_path('database/migrations_data/2026_07_27_000001_live_map_settings.php');
}

function findSetting(string $key): ?Setting
{
    return Setting::where('id', Setting::formatKey($key))->first();
}

/** A pre-change `acars` group setting, as an install carries it. */
function putAcarsSetting(string $key, string $value, string $name, string $type = 'int'): void
{
    $model = new Setting([
        'key'         => $key,
        'name'        => $name,
        'value'       => $value,
        'group'       => 'acars',
        'type'        => $type,
        'options'     => '',
        'description' => 'the old description',
    ]);
    $model->id = Setting::formatKey($key);
    $model->default = $value;
    $model->offset = 7;
    $model->order = 7;
    $model->save();
}

/**
 * What a real upgrade presents: the seeder has already run, so the new keys exist
 * at their defaults with the old `acars` keys still alongside them.
 */
function seedPreUpgradeState(string $liveTime = '12'): void
{
    putAcarsSetting('acars.live_time', $liveTime, 'Live Time');
    putAcarsSetting('acars.center_coords', '30.1945,-97.6699', 'Center Coords', 'text');
    putAcarsSetting('acars.default_zoom', '5', 'Default Zoom');
    putAcarsSetting('acars.update_interval', '60', 'Refresh Interval');
}

test('a customised live time survives the move to the tombstone setting', function (): void {
    seedPreUpgradeState(liveTime: '24');

    liveMapSettingsMigration()->up();

    $tombstone = findSetting('pireps.tombstone_time');

    // 24 hours, still meaning 24 hours: the unit is not converted.
    expect($tombstone)->not->toBeNull()
        ->and($tombstone->value)->toBe('24')
        ->and($tombstone->group)->toBe('pireps')
        ->and($tombstone->default)->toBe('12')
        ->and(findSetting('acars.live_time'))->toBeNull();
});

test('an untouched default lands on the default', function (): void {
    seedPreUpgradeState(liveTime: '12');

    liveMapSettingsMigration()->up();

    expect(findSetting('pireps.tombstone_time')->value)->toBe('12');
});

test('the display settings are regrouped with their values intact', function (): void {
    seedPreUpgradeState();
    Setting::where('id', Setting::formatKey('acars.center_coords'))->update(['value' => '51.4700,-0.4543']);
    Setting::where('id', Setting::formatKey('acars.default_zoom'))->update(['value' => '9']);

    liveMapSettingsMigration()->up();

    expect(findSetting('livemap.center_coords')->value)->toBe('51.4700,-0.4543')
        ->and(findSetting('livemap.center_coords')->group)->toBe('livemap')
        ->and(findSetting('livemap.default_zoom')->value)->toBe('9')
        ->and(findSetting('livemap.update_interval')->value)->toBe('60');

    expect(findSetting('acars.center_coords'))->toBeNull()
        ->and(findSetting('acars.default_zoom'))->toBeNull()
        ->and(findSetting('acars.update_interval'))->toBeNull();
});

test('no setting is left in the acars group', function (): void {
    seedPreUpgradeState();

    liveMapSettingsMigration()->up();

    expect(Setting::where('group', 'acars')->count())->toBe(0);
});

test('the two new timers are left to the seeder', function (): void {
    seedPreUpgradeState();

    liveMapSettingsMigration()->up();

    // No old key maps onto either, so the migration must not touch them.
    expect(findSetting('livemap.live_time')->value)->toBe('30')
        ->and(findSetting('livemap.live_time')->group)->toBe('livemap')
        ->and(findSetting('livemap.idle_time')->value)->toBe('60')
        ->and(findSetting('livemap.idle_time')->group)->toBe('livemap');
});

test('a re-run leaves a value the admin has since changed alone', function (): void {
    seedPreUpgradeState(liveTime: '24');
    liveMapSettingsMigration()->up();

    Setting::where('id', Setting::formatKey('pireps.tombstone_time'))->update(['value' => '6']);

    // The source is gone, so there is nothing to carry and nothing to clobber.
    liveMapSettingsMigration()->up();

    expect(findSetting('pireps.tombstone_time')->value)->toBe('6');
});

test('the migration is a no-op on an install that has already been through it', function (): void {
    liveMapSettingsMigration()->up();

    expect(findSetting('pireps.tombstone_time')->value)->toBe('12')
        ->and(findSetting('livemap.center_coords')->value)->toBe('30.1945,-97.6699')
        ->and(Setting::where('group', 'acars')->count())->toBe(0);
});

test('reversing restores the previous keys, names and descriptions', function (): void {
    seedPreUpgradeState(liveTime: '24');
    liveMapSettingsMigration()->up();

    liveMapSettingsMigration()->down();

    $liveTime = findSetting('acars.live_time');

    expect($liveTime)->not->toBeNull()
        ->and($liveTime->value)->toBe('24')
        ->and($liveTime->name)->toBe('Live Time')
        ->and($liveTime->group)->toBe('acars')
        ->and($liveTime->description)->toContain('Age of flights to show on the map in hours');

    expect(findSetting('acars.center_coords')->group)->toBe('acars')
        ->and(findSetting('acars.default_zoom')->group)->toBe('acars')
        ->and(findSetting('acars.update_interval')->group)->toBe('acars');

    expect(findSetting('pireps.tombstone_time'))->toBeNull();
});

test('reversing removes the settings that did not exist before', function (): void {
    seedPreUpgradeState();
    liveMapSettingsMigration()->up();

    liveMapSettingsMigration()->down();

    // No pre-change counterpart to rename back to.
    expect(findSetting('livemap.live_time'))->toBeNull()
        ->and(findSetting('livemap.idle_time'))->toBeNull();
});

test('a fresh install and an upgraded install agree on key, group and default', function (): void {
    $keys = [
        'pireps.tombstone_time',
        'livemap.live_time',
        'livemap.idle_time',
        'livemap.center_coords',
        'livemap.default_zoom',
        'livemap.update_interval',
    ];

    $snapshot = fn (): array => collect($keys)
        ->mapWithKeys(fn (string $key): array => [
            $key => [
                'key'     => findSetting($key)?->key,
                'group'   => findSetting($key)?->group,
                'default' => findSetting($key)?->default,
            ],
        ])
        ->all();

    // Pest's beforeEach has already seeded: this is the fresh install.
    $fresh = $snapshot();

    // Wind back to a pre-change install.
    Setting::query()->whereIn('id', array_map(Setting::formatKey(...), $keys))->delete();
    seedPreUpgradeState(liveTime: '24');

    // Replay the upgrade in Updater's order: seeders, then data migrations.
    new SettingsSeeder()->run();
    liveMapSettingsMigration()->up();

    expect($snapshot())->toBe($fresh);

    // The only thing an upgrade carries that a fresh install does not.
    expect(findSetting('pireps.tombstone_time')->value)->toBe('24');
});
