<?php

declare(strict_types=1);

use App\Enums\WeightUnit;
use App\Services\SettingService;
use App\Settings\GeneralSettings;
use App\Settings\UnitsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What the `setting()` / `setting_save()` helpers hand out and accept, one
 * declared setting per type. Values are written to the table directly so the
 * reads go through the settings classes' casting.
 */
beforeEach(function (): void {
    writeRawSetting('general.site_name', 'phpvms');
    writeRawSetting('general.telemetry', true);
    writeRawSetting('general.start_date', '2024-06-01T00:00:00+00:00');
    writeRawSetting('pilots.id_length', 10);
    writeRawSetting('fares.low_cost_multiplier', 1.5);
    writeRawSetting('units.weight', 'kg');
});

test('setting() returns string for string-typed setting', function (): void {
    expect(setting('general.site_name'))->toBe('phpvms');
});

test('setting() returns true bool for a stored true', function (): void {
    expect(setting('general.telemetry'))->toBeTrue();
});

test('setting() returns false bool for a stored false', function (): void {
    writeRawSetting('general.telemetry', false);

    expect(setting('general.telemetry'))->toBeFalse();
});

test('setting() returns an immutable date for date-typed setting', function (): void {
    $value = setting('general.start_date');

    expect($value)->toBeInstanceOf(CarbonImmutable::class)
        ->and($value->format('Y-m-d'))->toBe('2024-06-01');
});

test('a date read is not shared mutable state', function (): void {
    $nextDay = setting('general.start_date')->addDay();

    expect($nextDay->format('Y-m-d'))->toBe('2024-06-02')
        ->and(setting('general.start_date')->format('Y-m-d'))->toBe('2024-06-01');
});

test('setting() returns int for int-typed setting', function (): void {
    expect(setting('pilots.id_length'))->toBe(10);
});

test('setting() returns float for float-typed setting', function (): void {
    expect(setting('fares.low_cost_multiplier'))->toBe(1.5);
});

test('setting() returns an option setting as its string value', function (): void {
    expect(setting('units.weight'))->toBe('kg')
        ->and(app(UnitsSettings::class)->weight)->toBe(WeightUnit::KILOGRAMS);
});

test('setting() returns the declared default for a setting never saved', function (): void {
    DB::table('settings')->where('group', 'livemap')->delete();
    reloadSettings();

    expect(setting('livemap.default_zoom'))->toBe(5);
});

test('setting() returns default when key is missing', function (): void {
    expect(setting('general.nonexistent', 'fallback'))->toBe('fallback');
});

test('setting() returns null when key is missing and no default', function (): void {
    expect(setting('general.nonexistent'))->toBeNull();
});

test('setting() returns the default when the settings cannot be read', function (): void {
    $this->mock(SettingService::class)
        ->shouldReceive('retrieve')
        ->andThrow(new RuntimeException('settings unreadable'));

    expect(setting('general.site_name', 'phpVMS'))->toBe('phpVMS');
});

test('setting_save() persists a new value and a subsequent setting() returns it', function (): void {
    setting_save('general.site_name', 'NewName');

    expect(setting('general.site_name'))->toBe('NewName')
        ->and(app(GeneralSettings::class)->site_name)->toBe('NewName');
});

test('setting_save() stores a true bool and returns it', function (): void {
    expect(setting_save('general.telemetry', true))->toBeTrue()
        ->and(DB::table('settings')->where('group', 'general')->where('name', 'telemetry')->value('payload'))->toBe('true');
});

test('setting_save() stores a false bool and returns it', function (): void {
    expect(setting_save('general.telemetry', false))->toBeFalse()
        ->and(setting('general.telemetry'))->toBeFalse();
});

test('setting_save() does not create new settings — silently no-ops on missing key', function (): void {
    expect(setting_save('general.does_not_exist', 'x'))->toBeNull()
        ->and(DB::table('settings')->where('name', 'does_not_exist')->exists())->toBeFalse();
});

test('setting_save() rejects a value outside an option set', function (): void {
    expect(fn () => setting_save('units.weight', 'stone'))->toThrow(ValidationException::class)
        ->and(setting('units.weight'))->toBe('kg');
});

test('setting_save() rejects a value that is not of the declared type', function (): void {
    expect(fn () => setting_save('pilots.id_length', 'four'))->toThrow(ValidationException::class)
        ->and(setting('pilots.id_length'))->toBe(10);
});

test('a secret is stored encrypted and read back plain', function (): void {
    setting_save('simbrief.api_key', 'abc123');

    $stored = (string) DB::table('settings')->where('group', 'simbrief')->where('name', 'api_key')->value('payload');

    expect($stored)->not->toContain('abc123')
        ->and(setting('simbrief.api_key'))->toBe('abc123');

    app()->forgetScopedInstances();

    expect(setting('simbrief.api_key'))->toBe('abc123');
});
