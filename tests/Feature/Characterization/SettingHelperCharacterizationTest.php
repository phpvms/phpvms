<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `id` is not in `Setting::$fillable`, so `Setting::create(['id' => ...])`
 * drops it and triggers a NOT NULL violation. Production seeds via raw
 * `DB::table('settings')->insert(...)` (see `SeederService::addSetting()`).
 * This helper mirrors that path and lets `SettingObserver::creating()`
 * still apply via a fresh model fetch in the assertions.
 */
function seedSetting(array $attrs): void
{
    $row = array_merge([
        'id'          => Setting::formatKey($attrs['id']),
        'name'        => '',
        'key'         => $attrs['id'],
        'value'       => '',
        'type'        => 'string',
        'group'       => 'general',
        'order'       => 0,
        'description' => '',
    ], $attrs);
    $row['id'] = Setting::formatKey($row['id']);
    DB::table('settings')->insert($row);
}

beforeEach(function () {
    // Seed the 5 type branches.
    seedSetting(['id' => 'general.name',         'name' => 'Name',     'value' => 'phpvms',     'type' => 'string']);
    seedSetting(['id' => 'general.use_x',        'name' => 'UseX',     'value' => '1',          'type' => 'bool']);
    seedSetting(['id' => 'general.opening_date', 'name' => 'OpenDate', 'value' => '2024-06-01', 'type' => 'date']);
    seedSetting(['id' => 'general.max_pireps',   'name' => 'MaxP',     'value' => '10',         'type' => 'int']);
    seedSetting(['id' => 'general.fuel_factor',  'name' => 'Fuel',     'value' => '1.5',        'type' => 'float']);
});

test('setting() returns string for string-typed setting', function () {
    expect(setting('general.name'))->toBe('phpvms');
});

test('setting() returns true bool for value 1', function () {
    expect(setting('general.use_x'))->toBeTrue();
});

test('setting() returns false bool for value 0', function () {
    // SettingObserver::creating normalizes the id (dots → underscores), so
    // direct queries must use the formatted key (or the Setting::byKey scope).
    Setting::byKey('general.use_x')->update(['value' => '0']);
    expect(setting('general.use_x'))->toBeFalse();
});

test('setting() returns Carbon date for date-typed setting', function () {
    $value = setting('general.opening_date');
    expect($value)->toBeInstanceOf(Carbon::class)
        ->and($value->format('Y-m-d'))->toBe('2024-06-01');
});

test('setting() returns int for int-typed setting', function () {
    expect(setting('general.max_pireps'))->toBe(10);
});

test('setting() returns float for float-typed setting', function () {
    expect(setting('general.fuel_factor'))->toBe(1.5);
});

test('setting() returns default when key is missing', function () {
    expect(setting('general.nonexistent', 'fallback'))->toBe('fallback');
});

test('setting() returns null when key is missing and no default', function () {
    expect(setting('general.nonexistent'))->toBeNull();
});

test('setting_save() persists a new value and a subsequent setting() returns it', function () {
    setting_save('general.name', 'NewName');
    expect(setting('general.name'))->toBe('NewName');
});

test('setting_save() coerces a true bool to value 1', function () {
    setting_save('general.use_x', true);
    expect(Setting::find(Setting::formatKey('general.use_x'))->value)->toBe('1');
});

test('setting_save() coerces a false bool to value 0', function () {
    setting_save('general.use_x', false);
    expect(Setting::find(Setting::formatKey('general.use_x'))->value)->toBe('0');
});

test('setting_save() does not create new settings — silently no-ops on missing key', function () {
    setting_save('general.does_not_exist', 'x');
    expect(Setting::find(Setting::formatKey('general.does_not_exist')))->toBeNull();
});
