<?php

declare(strict_types=1);

use App\Addons\Support\AddonSettingsRegistry;
use App\Services\AddonSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AddonSettings\AlphaSettings;
use Tests\Support\AddonSettings\BetaSettings;
use Tests\Support\AddonSettings\WidgetNetworkSettings;
use Tests\Support\AddonSettings\WidgetSettings;

/**
 * Addon settings: declared as typed classes, scoped to the addon's registry_id,
 * read and written through addon_setting() / addon_setting_save().
 */
beforeEach(function (): void {
    registerAddonSettings('vendor/widget', [WidgetSettings::class, WidgetNetworkSettings::class], ['name' => 'Widget']);
});

// ── Declaration ─────────────────────────────────────────────────────────────

it('reads a declared setting as its default before it is ever saved', function (): void {
    expect(addon_setting('vendor/widget', 'retries'))->toBe(3)
        ->and(DB::table('addon_settings')->count())->toBe(0);
});

it('stores each section under the addon registry_id', function (): void {
    addon_setting_save('vendor/widget', 'retries', 5);

    expect(DB::table('addon_settings')->where('group', 'vendor/widget:network')->where('name', 'retries')->value('payload'))->toBe('5');
});

it('keeps a saved value when a new version adds a setting', function (): void {
    addon_setting_save('vendor/widget', 'flag', true);

    // The next boot registers a class that declares one more setting.
    AddonSettingsRegistry::flush();
    registerAddonSettings('vendor/widget', [WidgetSettings::class, WidgetNetworkSettings::class]);
    app()->forgetScopedInstances();

    expect(addon_setting('vendor/widget', 'flag'))->toBeTrue()
        ->and(addon_setting('vendor/widget', 'endpoint'))->toBe('https://example.test');
});

// ── Helper resolution ───────────────────────────────────────────────────────

it('resolves a setting by addon registry_id and by name', function (): void {
    addon_setting_save('vendor/widget', 'token', 'abc');

    expect(addon_setting('vendor/widget', 'token'))->toBe('abc')
        ->and(addon_setting('Widget', 'token'))->toBe('abc');
});

it('returns the default for an unknown addon or key', function (): void {
    expect(addon_setting('does-not-exist', 'k', 'fallback'))->toBe('fallback')
        ->and(addon_setting('vendor/widget', 'missing', 'fallback'))->toBe('fallback');
});

// ── Typed casting ───────────────────────────────────────────────────────────

it('casts values by declared type', function (): void {
    app(AddonSettingService::class)->storeManyFor('vendor/widget', [
        'flag'  => '1',
        'count' => '42',
        'ratio' => '0.25',
        'day'   => '2026-01-15',
        'unit'  => 'kg',
    ]);
    app()->forgetScopedInstances();

    expect(addon_setting('vendor/widget', 'flag'))->toBeTrue()
        ->and(addon_setting('vendor/widget', 'count'))->toBe(42)
        ->and(addon_setting('vendor/widget', 'ratio'))->toBe(0.25)
        ->and(addon_setting('vendor/widget', 'day'))->toBeInstanceOf(CarbonImmutable::class)
        ->and(addon_setting('vendor/widget', 'unit'))->toBe('kg');
});

it('hands out a date that callers cannot change for the next reader', function (): void {
    addon_setting_save('vendor/widget', 'day', '2026-01-15');

    $nextDay = addon_setting('vendor/widget', 'day')->addDay();

    expect($nextDay->format('Y-m-d'))->toBe('2026-01-16')
        ->and(addon_setting('vendor/widget', 'day')->format('Y-m-d'))->toBe('2026-01-15');
});

it('rejects a value that is not of the declared type', function (): void {
    expect(fn (): mixed => addon_setting_save('vendor/widget', 'count', 'many'))->toThrow(ValidationException::class)
        ->and(addon_setting('vendor/widget', 'count'))->toBe(0);
});

// ── Writes ──────────────────────────────────────────────────────────────────

it('persists a value via addon_setting_save and shows it to the next request', function (): void {
    addon_setting_save('vendor/widget', 'token', 'new');
    app()->forgetScopedInstances();

    expect(addon_setting('vendor/widget', 'token'))->toBe('new');
});

it('does not store to an unknown addon or key', function (): void {
    expect(addon_setting_save('nope', 'token', 'x'))->toBeNull()
        ->and(app(AddonSettingService::class)->store('vendor/widget', 'missing', 'x'))->toBeNull()
        ->and(DB::table('addon_settings')->count())->toBe(0);
});

// ── Isolation ───────────────────────────────────────────────────────────────

it('isolates settings with the same name across addons', function (): void {
    registerAddonSettings('addon/alpha', [AlphaSettings::class]);
    registerAddonSettings('addon/beta', [BetaSettings::class]);

    addon_setting_save('addon/alpha', 'api_token', 'alpha-token');
    addon_setting_save('addon/beta', 'api_token', 'beta-token');

    expect(addon_setting('addon/alpha', 'api_token'))->toBe('alpha-token')
        ->and(addon_setting('addon/beta', 'api_token'))->toBe('beta-token');
});

it('keeps an addon value across an uninstall and reinstall with the same registry_id', function (): void {
    addon_setting_save('vendor/widget', 'token', 'kept');

    DB::table('addons')->where('registry_id', 'vendor/widget')->delete();
    AddonSettingsRegistry::flush();
    registerAddonSettings('vendor/widget', [WidgetSettings::class, WidgetNetworkSettings::class], ['name' => 'Widget']);
    app()->forgetScopedInstances();

    expect(addon_setting('vendor/widget', 'token'))->toBe('kept');
});

it('rejects two sections declaring the same setting name', function (): void {
    expect(fn () => AddonSettingsRegistry::register(app(), 'addon/dupe', [AlphaSettings::class, BetaSettings::class]))
        ->toThrow(InvalidArgumentException::class)
        ->and(AddonSettingsRegistry::classesFor('addon/dupe'))->toBe([]);
});

it('leaves the core settings untouched', function (): void {
    $before = DB::table('settings')->count();

    addon_setting_save('vendor/widget', 'token', 'abc');

    expect(DB::table('settings')->count())->toBe($before);
});
