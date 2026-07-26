<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\AddonSettingSyncService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\Sample\Providers\SampleServiceProvider;

/**
 * Run the backfill_addon_registry_ids data migration in isolation.
 */
function runRegistryIdBackfill(): void
{
    (require base_path('database/migrations_data/2026_07_25_000000_backfill_addon_registry_ids.php'))->up();
}

/**
 * Drop a module.json with the given registry_id (or no registry_id key when
 * null) at a fresh temp directory and return its path.
 */
function makeManifestDir(?string $registryId): string
{
    $dir = sys_get_temp_dir().'/registry-id-backfill-'.uniqid('', true);
    File::ensureDirectoryExists($dir);

    $manifest = ['name' => 'Widget'];
    if ($registryId !== null) {
        $manifest['registry_id'] = $registryId;
    }

    File::put($dir.'/module.json', json_encode($manifest));

    return $dir;
}

// ── Backfill migration (Testing #2) ─────────────────────────────────────────

it('fills a null registry_id from a manifest that declares one', function (): void {
    $dir = makeManifestDir('vendor/widget');
    $addon = Addon::factory()->create(['registry_id' => null, 'path' => $dir]);

    runRegistryIdBackfill();

    expect($addon->fresh()->registry_id)->toBe('vendor/widget');
});

it('leaves an already-populated registry_id untouched', function (): void {
    $dir = makeManifestDir('vendor/other');
    $addon = Addon::factory()->create(['registry_id' => 'vendor/original', 'path' => $dir]);

    runRegistryIdBackfill();

    expect($addon->fresh()->registry_id)->toBe('vendor/original');
});

it('leaves the row null when the manifest declares no registry_id', function (): void {
    $dir = makeManifestDir(null);
    $addon = Addon::factory()->create(['registry_id' => null, 'path' => $dir]);

    runRegistryIdBackfill();

    expect($addon->fresh()->registry_id)->toBeNull();
});

it('leaves the row null when the manifest file is missing', function (): void {
    $addon = Addon::factory()->create([
        'registry_id' => null,
        'path'        => sys_get_temp_dir().'/registry-id-backfill-missing-'.uniqid('', true),
    ]);

    runRegistryIdBackfill();

    expect($addon->fresh()->registry_id)->toBeNull();
});

it('leaves the row null when the manifest file is invalid JSON', function (): void {
    $dir = sys_get_temp_dir().'/registry-id-backfill-'.uniqid('', true);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', '{not valid json');

    $addon = Addon::factory()->create(['registry_id' => null, 'path' => $dir]);

    runRegistryIdBackfill();

    expect($addon->fresh()->registry_id)->toBeNull();
});

it('normalises a blank or whitespace-only declared registry_id to null', function (): void {
    $dir = makeManifestDir('   ');
    $addon = Addon::factory()->create(['registry_id' => null, 'path' => $dir]);

    runRegistryIdBackfill();

    expect($addon->fresh()->registry_id)->toBeNull();
});

// ── Sync fallback (Testing #3) ──────────────────────────────────────────────

it('falls back to namespace and logs when a declared registry_id matches no row', function (): void {
    $addon = Addon::factory()->create(['registry_id' => null, 'namespace' => 'Modules\\Widget']);

    $entry = new AddonBootCache(
        name: 'Widget',
        alias: 'widget',
        type: 'module',
        registryId: 'vendor/widget',
        version: null,
        namespace: 'Modules\\Widget',
        providers: [],
        path: $addon->path,
        autoloadPath: $addon->path,
        layout: 'app',
        description: null,
        enabled: true,
    );

    $fakeCache = mock(BootCache::class);
    $fakeCache->allows('enabled')->andReturns(collect([$entry]));
    app()->instance(BootCache::class, $fakeCache);

    Log::spy();

    app(AddonSettingSyncService::class)->sync();

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('AddonSettingSync: no addon row for registry_id vendor/widget; falling back to namespace');
});

// ── Regression (Testing #6) ─────────────────────────────────────────────────

it('syncs declared settings when the addon row has a null registry_id but the boot cache declares one', function (): void {
    // The namespace here is a fixture value distinct from the real bundled
    // Sample module's `Modules\Sample` row (namespace is unique) — only the
    // real Sample provider class matters for resolving the declared schema,
    // and the fallback lookup just needs the row and entry namespaces to match.
    $namespace = 'Modules\\Sample'.uniqid('', true);
    $addon = Addon::factory()->create(['registry_id' => null, 'namespace' => $namespace]);

    $entry = new AddonBootCache(
        name: 'Sample',
        alias: 'sample',
        type: 'module',
        registryId: 'phpvms/sample',
        version: null,
        namespace: $namespace,
        providers: [SampleServiceProvider::class],
        path: $addon->path,
        autoloadPath: $addon->path,
        layout: 'app',
        description: null,
        enabled: true,
    );

    $fakeCache = mock(BootCache::class);
    $fakeCache->allows('enabled')->andReturns(collect([$entry]));
    app()->instance(BootCache::class, $fakeCache);

    Log::spy();

    app(AddonSettingSyncService::class)->sync();

    $greeting = AddonSetting::where('addon_id', $addon->id)->where('key', 'greeting')->first();

    expect($greeting)->not->toBeNull()
        ->and($greeting->value)->toBe('Hello from the Sample module!');
});
