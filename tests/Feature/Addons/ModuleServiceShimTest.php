<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use App\Services\ModuleService;

beforeEach(function (): void {
    // Fresh boot cache + DB rows for each test.
    app(BootCache::class)->delete();
    app(AddonDiscoveryService::class)->run();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

// ─────────────────────────────────────────────────────────────────────────────
// I2 regression: updateModule() must not throw CommandNotFoundException
// ─────────────────────────────────────────────────────────────────────────────

it('updateModule(Sample, false) does not throw and disables the addon', function (): void {
    $service = app(ModuleService::class);

    // Must not throw (previously crashed via Artisan::call('module:migrate', …)).
    $service->updateModule('Sample', false);

    $addon = Addon::query()->where('path', 'LIKE', '%modules/Sample')->firstOrFail();
    expect($addon->enabled)->toBeFalse();

    // Boot cache must exclude Sample.
    $cached = app(BootCache::class)->read();
    expect(array_map(fn (AddonBootCache $r): string => $r->name, $cached))->not->toContain('Sample');
});

it('updateModule(Sample, true) does not throw and re-enables the addon', function (): void {
    $service = app(ModuleService::class);

    // Disable first so we have something to re-enable.
    $service->updateModule('Sample', false);

    $service->updateModule('Sample', true);

    $addon = Addon::query()->where('path', 'LIKE', '%modules/Sample')->firstOrFail();
    expect($addon->enabled)->toBeTrue();

    // Boot cache must include Sample again.
    $cached = app(BootCache::class)->read();
    expect(array_map(fn (AddonBootCache $r): string => $r->name, $cached))->toContain('Sample');
});

it('deleteModule() does not throw and removes the addon DB row', function (): void {
    // Use a fixed name (not uniqid) so Module::find(name) can match it.
    $addonName = 'ThrowawayServiceDel';
    $tmpDir = config('addons.paths.base').'/'.$addonName;

    if (is_dir($tmpDir)) {
        // Clean up any leftover from a previous interrupted test run.
        @unlink($tmpDir.'/module.json');
        @rmdir($tmpDir);
    }

    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'      => $addonName,
        'alias'     => strtolower($addonName),
        'providers' => [],
    ]));

    app(AddonDiscoveryService::class)->run();

    $throwaway = Addon::query()->where('path', $tmpDir)->firstOrFail();
    $throwawayId = $throwaway->id;

    // Remove module.json so AddonRuntimeService won't re-discover it on next prime.
    unlink($tmpDir.'/module.json');

    $service = app(ModuleService::class);

    // Must not throw.
    $service->deleteModule($addonName);

    // DB row gone.
    expect(Addon::query()->where('id', $throwawayId)->first())->toBeNull();

    // Boot cache no longer lists it.
    $cached = app(BootCache::class)->read();
    expect(array_map(fn (AddonBootCache $r): string => $r->path, $cached))->not->toContain($tmpDir);

    // Cleanup temp dir.
    @rmdir($tmpDir);
});
