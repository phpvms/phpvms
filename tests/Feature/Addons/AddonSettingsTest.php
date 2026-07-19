<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonSetting;
use App\Models\Setting;
use App\Services\AddonSettingService;
use App\Services\AddonSettingSyncService;
use Illuminate\Support\Carbon;

/**
 * Create an addon row plus one setting and return [Addon, AddonSetting].
 *
 * @return array{0: Addon, 1: AddonSetting}
 */
function makeAddonSetting(array $addon = [], array $setting = []): array
{
    $addonModel = Addon::factory()->create($addon);
    $settingModel = AddonSetting::factory()->create(['addon_id' => $addonModel->id] + $setting);

    return [$addonModel, $settingModel];
}

// ── Sync (8.1) ──────────────────────────────────────────────────────────────

it('inserts declared keys with their defaults on first sync', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonSettingSyncService::class)->sync();

    $sample = Addon::where('namespace', 'Modules\\Sample')->firstOrFail();

    $greeting = AddonSetting::where('addon_id', $sample->id)->where('key', 'greeting')->first();

    expect($greeting)->not->toBeNull()
        ->and($greeting->value)->toBe('Hello from the Sample module!')
        ->and($greeting->default)->toBe('Hello from the Sample module!')
        ->and($greeting->alias)->toBe('sample');
});

it('preserves a user-edited value but reconciles metadata on re-sync', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    $sync = app(AddonSettingSyncService::class);
    $sync->sync();

    $sample = Addon::where('namespace', 'Modules\\Sample')->firstOrFail();
    $row = AddonSetting::where('addon_id', $sample->id)->where('key', 'greeting')->firstOrFail();

    // User edits the value and we corrupt the description to prove it gets reconciled.
    $row->update(['value' => 'Custom greeting', 'description' => 'stale']);

    $sync->sync();
    $row->refresh();

    expect($row->value)->toBe('Custom greeting')
        ->and($row->description)->toBe('Text returned by sample_module_greeting()');
});

it('is idempotent — repeated syncs do not duplicate rows', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    $sync = app(AddonSettingSyncService::class);

    $sync->sync();

    $sample = Addon::where('namespace', 'Modules\\Sample')->firstOrFail();
    $countAfterFirst = AddonSetting::where('addon_id', $sample->id)->count();

    $sync->sync();
    $countAfterSecond = AddonSetting::where('addon_id', $sample->id)->count();

    expect($countAfterFirst)->toBe(5)
        ->and($countAfterSecond)->toBe($countAfterFirst);
});

// ── No HasSettings (8.2) ─────────────────────────────────────────────────────

it('registers no rows for an addon whose providers do not declare settings', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonSettingSyncService::class)->sync();

    $awards = Addon::where('namespace', 'Modules\\Awards')->first();

    if ($awards === null) {
        expect(true)->toBeTrue(); // Awards not bundled in this checkout — nothing to assert.

        return;
    }

    expect(AddonSetting::where('addon_id', $awards->id)->count())->toBe(0);
});

// ── Helper resolution (8.3) ──────────────────────────────────────────────────

it('resolves a setting by addon alias', function (): void {
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonSettingSyncService::class)->sync();

    expect(addon_setting('sample', 'greeting'))->toBe('Hello from the Sample module!');
});

it('resolves a setting by addon registry_id', function (): void {
    makeAddonSetting(
        ['registry_id' => 'vendor/widget', 'name' => 'Widget'],
        ['key' => 'token', 'value' => 'abc', 'type' => 'text'],
    );

    expect(addon_setting('vendor/widget', 'token'))->toBe('abc');
});

it('returns the default for an unknown addon or key', function (): void {
    expect(addon_setting('does-not-exist', 'k', 'fallback'))->toBe('fallback');

    makeAddonSetting(['registry_id' => 'vendor/widget'], ['key' => 'known']);
    expect(addon_setting('vendor/widget', 'missing', 'fallback'))->toBe('fallback');
});

// ── Typed casting (8.4) ──────────────────────────────────────────────────────

it('casts values by type', function (): void {
    [$addon] = makeAddonSetting(['registry_id' => 'cast/test'], ['key' => 'flag', 'type' => 'boolean', 'value' => '1']);
    AddonSetting::factory()->create(['addon_id' => $addon->id, 'key' => 'count', 'type' => 'int', 'value' => '42']);
    AddonSetting::factory()->create(['addon_id' => $addon->id, 'key' => 'ratio', 'type' => 'float', 'value' => '0.25']);
    AddonSetting::factory()->create(['addon_id' => $addon->id, 'key' => 'day', 'type' => 'date', 'value' => '2026-01-15']);

    expect(addon_setting('cast/test', 'flag'))->toBeTrue()
        ->and(addon_setting('cast/test', 'count'))->toBe(42)
        ->and(addon_setting('cast/test', 'ratio'))->toBe(0.25)
        ->and(addon_setting('cast/test', 'day'))->toBeInstanceOf(Carbon::class);
});

// ── Write + cache (8.5) ──────────────────────────────────────────────────────

it('persists a value via addon_setting_save and reads it back fresh', function (): void {
    makeAddonSetting(['registry_id' => 'vendor/widget'], ['key' => 'token', 'value' => 'old', 'type' => 'text']);

    addon_setting_save('vendor/widget', 'token', 'new');

    expect(addon_setting('vendor/widget', 'token'))->toBe('new')
        ->and(AddonSetting::query()->where('key', 'token')->value('value'))->toBe('new');
});

it('does not store to an unknown addon or key', function (): void {
    [$addon] = makeAddonSetting(['registry_id' => 'vendor/widget'], ['key' => 'token', 'value' => 'old']);

    expect(addon_setting_save('nope', 'token', 'x'))->toBeNull()
        ->and(app(AddonSettingService::class)->store('vendor/widget', 'missing', 'x'))->toBeNull()
        ->and(AddonSetting::where('addon_id', $addon->id)->where('key', 'token')->value('value'))->toBe('old');
});

// ── Isolation (8.6) ──────────────────────────────────────────────────────────

it('isolates settings with the same key across addons', function (): void {
    makeAddonSetting(['registry_id' => 'addon/alpha'], ['key' => 'api_token', 'value' => 'alpha-token', 'type' => 'text']);
    makeAddonSetting(['registry_id' => 'addon/beta'], ['key' => 'api_token', 'value' => 'beta-token', 'type' => 'text']);

    expect(addon_setting('addon/alpha', 'api_token'))->toBe('alpha-token')
        ->and(addon_setting('addon/beta', 'api_token'))->toBe('beta-token');
});

// ── Core settings unaffected (8.8) ──────────────────────────────────────────

it('leaves the core settings table untouched', function (): void {
    $before = Setting::count();

    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonSettingSyncService::class)->sync();

    expect(Setting::count())->toBe($before)
        ->and(Setting::where('id', 'like', 'sample%')->count())->toBe(0);
});
