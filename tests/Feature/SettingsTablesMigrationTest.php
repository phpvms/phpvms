<?php

declare(strict_types=1);

use App\Settings\CaptchaSettings;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AddonSettings\WidgetSettings;

/**
 * The move to typed settings: the old `settings` / `addon_settings` tables are
 * kept as backups, new tables take their names, and the values are copied over
 * (by the schema migration, then again by the last data migration).
 */
function settingsTablesMigration(): object
{
    return require base_path('database/migrations/2026_11_10_075533_move_settings_to_typed_settings_tables.php');
}

function settingsCopyDataMigration(): object
{
    return require base_path('database/migrations_data/2026_11_10_100000_copy_settings_from_backups.php');
}

/**
 * Wind the schema back to before the move, with old-style rows in both tables:
 * what an install that upgrades has.
 */
function preUpgradeSettingsTables(): void
{
    settingsTablesMigration()->down();

    seedLegacySettingsInto('settings');

    DB::table('settings')->where('id', 'pilots_id_length')->update(['value' => '5']);
    DB::table('settings')->where('id', 'captcha_secret_key')->update(['value' => 'hunter2']);
    DB::table('settings')->where('id', 'va_global_id')->update(['value' => 'GLOBALID']);

    DB::table('addon_settings')->insert([
        ['registry_id' => 'vendor/widget', 'alias' => 'widget', 'order' => 0, 'key' => 'token', 'name' => 'Token', 'value' => 'abc', 'default' => '', 'group' => 'General', 'type' => 'text', 'options' => '', 'description' => ''],
        ['registry_id' => 'vendor/widget', 'alias' => 'widget', 'order' => 1, 'key' => 'count', 'name' => 'Count', 'value' => '7', 'default' => '0', 'group' => 'General', 'type' => 'int', 'options' => '', 'description' => ''],
        ['registry_id' => 'vendor/widget', 'alias' => 'widget', 'order' => 2, 'key' => 'gone', 'name' => 'Gone', 'value' => 'old', 'default' => '', 'group' => 'General', 'type' => 'text', 'options' => '', 'description' => ''],
    ]);
}

/**
 * The old seeder rows, written to a table with the old schema.
 */
function seedLegacySettingsInto(string $table): void
{
    $positions = [];

    foreach (require base_path('tests/data/legacy_settings.php') as $setting) {
        $position = $positions[$setting['group']] = ($positions[$setting['group']] ?? -1) + 1;

        DB::table($table)->insert([
            'id'          => str_replace('.', '_', $setting['key']),
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
        ]);
    }
}

it('keeps the old tables as backups and gives the new tables the package schema', function (): void {
    preUpgradeSettingsTables();
    $before = DB::table('settings')->orderBy('id')->get()->toArray();

    settingsTablesMigration()->up();

    expect(DB::table('settings_backup')->orderBy('id')->get()->toArray())->toEqual($before)
        ->and(DB::table('addon_settings_backup')->count())->toBe(3)
        ->and(Schema::getColumnListing('settings'))->toContain('group', 'name', 'locked', 'payload')
        ->and(Schema::getColumnListing('addon_settings'))->toContain('group', 'name', 'locked', 'payload');

    foreach (['settings', 'addon_settings'] as $table) {
        $unique = collect(Schema::getIndexes($table))->first(fn (array $index): bool => $index['unique'] && $index['columns'] === ['group', 'name']);

        expect($unique)->not->toBeNull();
    }
});

it('copies the values as soon as the schema migration runs', function (): void {
    preUpgradeSettingsTables();

    settingsTablesMigration()->up();
    reloadSettings();

    expect(setting('pilots.id_length'))->toBe(5)
        ->and(setting('registry.va_global_id'))->toBe('GLOBALID')
        ->and(setting('va_global_id'))->toBe('GLOBALID');
});

it('encrypts a secret it copies', function (): void {
    preUpgradeSettingsTables();

    settingsTablesMigration()->up();
    reloadSettings();

    expect((string) DB::table('settings')->where('group', 'captcha')->where('name', 'secret_key')->value('payload'))->not->toContain('hunter2')
        ->and(app(CaptchaSettings::class)->secret_key)->toBe('hunter2');
});

it('restores the old tables and their rows on rollback', function (): void {
    preUpgradeSettingsTables();
    $before = DB::table('settings')->orderBy('id')->get()->toArray();

    settingsTablesMigration()->up();
    setting_save('pilots.id_length', 9);
    settingsTablesMigration()->down();

    expect(Schema::hasTable('settings_backup'))->toBeFalse()
        ->and(Schema::hasTable('addon_settings_backup'))->toBeFalse()
        ->and(DB::table('settings')->orderBy('id')->get()->toArray())->toEqual($before)
        ->and(DB::table('addon_settings')->where('key', 'count')->value('value'))->toBe('7');

    settingsTablesMigration()->up();
});

it('replaces a value it cannot cast with the default and logs it', function (): void {
    seedLegacySettings();
    DB::table('settings_backup')->where('id', 'units_weight')->update(['value' => 'stone']);
    Log::spy();

    copyLegacySettings();

    expect(setting('units.weight'))->toBe('lbs');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'units.weight') && str_contains($message, 'stone'))->once();
});

it('carries a renamed key onto its current key', function (): void {
    seedLegacySettings();
    DB::table('settings_backup')->insert(['id' => 'general_auto_airport_lookup', 'key' => 'general.auto_airport_lookup', 'name' => '', 'value' => '0', 'group' => 'general', 'type' => 'boolean']);
    DB::table('settings_backup')->where('id', 'airports_auto_airport_lookup')->delete();

    copyLegacySettings();

    expect(setting('airports.auto_airport_lookup'))->toBeFalse();
});

it('gives the same result when it runs again, and never writes the backup', function (): void {
    seedLegacySettings();
    DB::table('settings_backup')->where('id', 'pilots_id_length')->update(['value' => '6']);
    $backup = DB::table('settings_backup')->orderBy('id')->get()->toArray();

    // Encrypted payloads differ on every write (a fresh IV), so compare what
    // each setting reads as.
    $values = fn (): array => app(SettingsRegistry::class)->all()->map(fn (SettingDefinition $definition): mixed => setting($definition->key))->all();

    copyLegacySettings();
    $first = $values();
    $rows = DB::table('settings')->count();
    copyLegacySettings();

    expect($values())->toEqual($first)
        ->and(DB::table('settings')->count())->toBe($rows)
        ->and(DB::table('settings_backup')->orderBy('id')->get()->toArray())->toEqual($backup)
        ->and(setting('pilots.id_length'))->toBe(6);
});

it('carries over what a pending older data migration wrote to the backup', function (): void {
    seedLegacySettings();

    // An older data migration still to run on this upgrade writes the backup.
    $migration = require base_path('database/migrations_data/2026_10_30_000000_hide_theme_setting.php');
    $migration->up();
    DB::table('settings_backup')->where('id', 'pilots_auto_leave_days')->update(['value' => '45']);

    settingsCopyDataMigration()->up();
    reloadSettings();

    expect(setting('pilots.auto_leave_days'))->toBe(45);
});

it('copies stored addon values under the addon registry_id and section', function (): void {
    preUpgradeSettingsTables();

    settingsTablesMigration()->up();
    registerAddonSettings('vendor/widget', [WidgetSettings::class]);
    app()->forgetScopedInstances();

    expect(addon_setting('vendor/widget', 'token'))->toBe('abc')
        ->and(addon_setting('vendor/widget', 'count'))->toBe(7);
});

it('keeps and logs stored addon values the addon no longer declares', function (): void {
    preUpgradeSettingsTables();
    settingsTablesMigration()->up();
    registerAddonSettings('vendor/widget', [WidgetSettings::class]);
    Log::spy();

    settingsCopyDataMigration()->up();

    expect(DB::table('addon_settings')->where('group', 'vendor/widget:general')->where('name', 'gone')->value('payload'))->toBe('"old"')
        ->and(DB::table('addon_settings_backup')->where('key', 'gone')->value('value'))->toBe('old');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, 'vendor/widget') && str_contains($message, 'gone'))->once();
});

it('keeps the addon backup when an addon setting is changed after the upgrade', function (): void {
    preUpgradeSettingsTables();
    settingsTablesMigration()->up();
    registerAddonSettings('vendor/widget', [WidgetSettings::class]);

    addon_setting_save('vendor/widget', 'token', 'changed');

    expect(DB::table('addon_settings_backup')->where('key', 'token')->value('value'))->toBe('abc')
        ->and(addon_setting('vendor/widget', 'token'))->toBe('changed');
});
