<?php

declare(strict_types=1);

use App\Features\Addons\AddonRegistry;
use App\Features\Addons\Models\AddonBootCache;
use App\Features\Addons\Support\AddonSettingsRegistry;
use App\Features\Addons\Support\BootCache;
use App\Models\Addon;
use App\Services\AddonSettingService;
use Illuminate\Support\Facades\DB;
use Modules\Sample\Providers\SampleServiceProvider;
use Tests\Support\AddonSettings\WidgetSettings;

/**
 * A boot-cache entry for the real Sample module's provider, so the sync has a
 * declared settings schema to work from.
 */
function sampleBootCacheEntry(string $registryId, Addon $addon): AddonBootCache
{
    return new AddonBootCache(
        name: 'Sample',
        alias: 'sample',
        type: 'module',
        registryId: $registryId,
        version: null,
        namespace: $addon->namespace,
        providers: [SampleServiceProvider::class],
        path: $addon->path,
        autoloadPath: $addon->path,
        layout: 'app',
        description: null,
        enabled: true,
    );
}

/**
 * Point the container's BootCache at a single fixed entry.
 */
function fakeBootCache(AddonBootCache $entry): void
{
    $fakeCache = mock(BootCache::class);
    $fakeCache->allows('enabled')->andReturns(collect([$entry]));
    app()->instance(BootCache::class, $fakeCache);
}

// ── registry_id is the identity ─────────────────────────────────────────────

it('leaves no addon row without a registry_id after migrating', function (): void {
    expect(Addon::query()->whereNull('registry_id')->count())->toBe(0)
        ->and(Addon::query()->where('registry_id', '')->count())->toBe(0);
});

it('keys addon settings on the registry_id, not on the addon row', function (): void {
    // The rows are keyed on the manifest's registry_id, not a foreign key, so
    // an addon's settings can be saved before discovery has inserted its row.
    AddonSettingsRegistry::register(app(), 'vendor/not-installed', [WidgetSettings::class]);

    app(AddonSettingService::class)->storeFor('vendor/not-installed', 'token', 'early');

    expect(DB::table('addon_settings')->where('group', 'vendor/not-installed:general')->where('name', 'token')->value('payload'))->toBe('"early"');
});

// ── Uninstall keeps values unless the operator says otherwise ───────────────

it("keeps an addon's settings when it is deleted without removing its data", function (): void {
    $addon = Addon::factory()->create(['name' => 'Keeper', 'registry_id' => 'vendor/keeper']);
    storeAddonSettingRow('vendor/keeper', 'token', 'kept');

    app(AddonRegistry::class)->delete('Keeper');

    expect(Addon::query()->where('registry_id', 'vendor/keeper')->exists())->toBeFalse()
        ->and(addonSettingRowsExist('vendor/keeper'))->toBeTrue();
});

it('reattaches kept settings to a reinstalled addon', function (): void {
    $addon = Addon::factory()->create(['name' => 'Keeper', 'registry_id' => 'vendor/keeper']);
    storeAddonSettingRow('vendor/keeper', 'token', 'kept');

    app(AddonRegistry::class)->delete('Keeper');

    // Reinstall: a brand new row, and therefore a brand new id.
    $reinstalled = Addon::factory()->create(['name' => 'Keeper', 'registry_id' => 'vendor/keeper']);
    AddonSettingsRegistry::register(app(), 'vendor/keeper', [WidgetSettings::class]);

    expect($reinstalled->id)->not->toBe($addon->id)
        ->and(addon_setting('vendor/keeper', 'token'))->toBe('kept');
});

it("deletes an addon's settings when its data is removed", function (): void {
    Addon::factory()->create(['name' => 'Purged', 'registry_id' => 'vendor/purged']);
    storeAddonSettingRow('vendor/purged', 'token', 'gone');
    storeAddonSettingRow('vendor/purged_more', 'token', 'kept');

    app(AddonRegistry::class)->delete('Purged', removeTables: true);

    // `_` must not match as a LIKE wildcard: a sibling registry_id keeps its rows.
    expect(DB::table('addon_settings')->where('group', 'vendor/purged:general')->exists())->toBeFalse()
        ->and(DB::table('addon_settings')->where('group', 'vendor/purged_more:general')->exists())->toBeTrue();
});
