<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\AddonSettingSyncService;
use Modules\Sample\Providers\SampleServiceProvider;

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

it('keys synced settings on the boot cache registry_id', function (): void {
    $addon = Addon::query()->where('registry_id', 'phpvms/sample')->firstOrFail();
    fakeBootCache(sampleBootCacheEntry('phpvms/sample', $addon));

    app(AddonSettingSyncService::class)->sync();

    $greeting = AddonSetting::where('registry_id', 'phpvms/sample')->where('key', 'greeting')->first();

    expect($greeting)->not->toBeNull()
        ->and($greeting->value)->toBe('Hello from the Sample module!');
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

it('syncs settings for an addon that has no row yet', function (): void {
    // The rows are keyed on the manifest's registry_id, not a foreign key, so
    // the sync no longer depends on discovery having inserted the addon first.
    $addon = Addon::query()->where('registry_id', 'phpvms/sample')->firstOrFail();
    fakeBootCache(sampleBootCacheEntry('vendor/not-installed', $addon));

    app(AddonSettingSyncService::class)->sync();

    expect(AddonSetting::where('registry_id', 'vendor/not-installed')->exists())->toBeTrue();
})->skip(sampleAddonMissing(...), 'No sample addon on disk; it is not tracked in this repo.');

// ── Uninstall keeps values unless the operator says otherwise ───────────────

it("keeps an addon's settings when it is deleted without removing its data", function (): void {
    $addon = Addon::factory()->create(['name' => 'Keeper', 'registry_id' => 'vendor/keeper']);
    AddonSetting::factory()->create([
        'registry_id' => 'vendor/keeper',
        'key'         => 'token',
        'value'       => 'kept',
    ]);

    app(AddonRegistry::class)->delete('Keeper');

    expect(Addon::query()->where('registry_id', 'vendor/keeper')->exists())->toBeFalse()
        ->and(AddonSetting::where('registry_id', 'vendor/keeper')->value('value'))->toBe('kept');
});

it('reattaches kept settings to a reinstalled addon', function (): void {
    $addon = Addon::factory()->create(['name' => 'Keeper', 'registry_id' => 'vendor/keeper']);
    AddonSetting::factory()->create([
        'registry_id' => 'vendor/keeper',
        'key'         => 'token',
        'value'       => 'kept',
    ]);

    app(AddonRegistry::class)->delete('Keeper');

    // Reinstall: a brand new row, and therefore a brand new id.
    $reinstalled = Addon::factory()->create(['name' => 'Keeper', 'registry_id' => 'vendor/keeper']);

    expect($reinstalled->id)->not->toBe($addon->id)
        ->and(addon_setting('vendor/keeper', 'token'))->toBe('kept');
});

it("deletes an addon's settings when its data is removed", function (): void {
    Addon::factory()->create(['name' => 'Purged', 'registry_id' => 'vendor/purged']);
    AddonSetting::factory()->create(['registry_id' => 'vendor/purged', 'key' => 'token']);

    app(AddonRegistry::class)->delete('Purged', removeTables: true);

    expect(AddonSetting::where('registry_id', 'vendor/purged')->exists())->toBeFalse();
});
