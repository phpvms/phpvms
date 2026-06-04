<?php

declare(strict_types=1);

use App\Addons\BootCache;
use App\Addons\Compat\ModuleRepository;
use App\Addons\Compat\ModuleShim;
use App\Addons\ManifestParser;
use App\Addons\PrimeService;
use App\Models\Addon;
use Nwidart\Modules\Exceptions\ModuleNotFoundException;

beforeEach(function (): void {
    // Ensure a fresh boot cache for each test.
    app(BootCache::class)->delete();

    // Seed DB rows + boot cache via PrimeService so tests start from a known state.
    app(PrimeService::class)->run();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. all() returns a collection keyed by name
// ─────────────────────────────────────────────────────────────────────────────

it('all() returns collection keyed by module name containing the 3 bundled modules', function (): void {
    $repo = app(ModuleRepository::class);
    $all = $repo->all();

    expect($all)->toHaveKey('Sample')
        ->toHaveKey('Awards')
        ->toHaveKey('VMSAcars');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. find() returns a shim with correct attributes
// ─────────────────────────────────────────────────────────────────────────────

it('find(Sample) returns a shim with correct name, path, enabled, and lowerName', function (): void {
    $repo = app(ModuleRepository::class);
    $shim = $repo->find('Sample');

    expect($shim)->not->toBeNull()
        ->and($shim->getName())->toBe('Sample')
        ->and($shim->getLowerName())->toBe('sample')
        ->and($shim->isEnabled())->toBeTrue()
        ->and($shim->getPath())->toEndWith('modules/Sample');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. find() returns null for unknown module
// ─────────────────────────────────────────────────────────────────────────────

it('find(DoesNotExist) returns null', function (): void {
    $repo = app(ModuleRepository::class);

    expect($repo->find('DoesNotExist'))->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. findOrFail() returns shim or throws
// ─────────────────────────────────────────────────────────────────────────────

it('findOrFail(Sample) returns a shim', function (): void {
    $repo = app(ModuleRepository::class);
    $shim = $repo->findOrFail('Sample');

    expect($shim)->not->toBeNull()
        ->and($shim->getName())->toBe('Sample');
});

it('findOrFail(Nope) throws ModuleNotFoundException', function (): void {
    $repo = app(ModuleRepository::class);

    expect(fn () => $repo->findOrFail('Nope'))
        ->toThrow(ModuleNotFoundException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. allEnabled() excludes a disabled addon
// ─────────────────────────────────────────────────────────────────────────────

it('allEnabled() excludes an addon after setActive(false) and re-prime', function (): void {
    $repo = app(ModuleRepository::class);

    $shim = $repo->find('Sample');
    expect($shim)->not->toBeNull();

    // Disable via shim (also re-primes the cache).
    $shim->setActive(false);

    // Re-resolve repo to get fresh state.
    $repo2 = app(ModuleRepository::class);
    $enabled = $repo2->allEnabled();

    expect($enabled)->not->toHaveKey('Sample')
        ->toHaveKey('Awards')
        ->toHaveKey('VMSAcars');
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. isEnabled()
// ─────────────────────────────────────────────────────────────────────────────

it('isEnabled(Sample) returns true', function (): void {
    $repo = app(ModuleRepository::class);

    expect($repo->isEnabled('Sample'))->toBeTrue();
});

it('isEnabled(Nope) returns false', function (): void {
    $repo = app(ModuleRepository::class);

    expect($repo->isEnabled('Nope'))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. getExtraPath()
// ─────────────────────────────────────────────────────────────────────────────

it('getExtraPath(Awards) returns getPath()/Awards', function (): void {
    $repo = app(ModuleRepository::class);
    $shim = $repo->find('Sample');

    expect($shim)->not->toBeNull()
        ->and($shim->getExtraPath('Awards'))->toBe($shim->getPath().'/Awards');
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. config()
// ─────────────────────────────────────────────────────────────────────────────

it('config(namespace) returns Modules', function (): void {
    $repo = app(ModuleRepository::class);

    expect($repo->config('namespace'))->toBe('Modules');
});

it('config(unknown, default) returns the default', function (): void {
    $repo = app(ModuleRepository::class);

    expect($repo->config('something', 'x'))->toBe('x');
});

// ─────────────────────────────────────────────────────────────────────────────
// 9. setActive() persists to DB and regenerates boot cache
// ─────────────────────────────────────────────────────────────────────────────

it('setActive(false) updates DB and removes Sample from boot cache; setActive(true) re-includes it', function (): void {
    $repo = app(ModuleRepository::class);
    $cache = app(BootCache::class);

    $shim = $repo->find('Sample');
    expect($shim)->not->toBeNull();

    // Disable.
    $shim->setActive(false);

    // DB check.
    $addonRow = Addon::query()->where('path', 'LIKE', '%modules/Sample')->first();
    expect($addonRow)->not->toBeNull()
        ->and($addonRow->enabled)->toBeFalse();

    // Boot cache check — Sample must be absent.
    $cached = $cache->read();
    $names = array_column($cached, 'name');
    expect($names)->not->toContain('Sample');

    // Re-enable.
    $shim->setActive(true);

    $addonRow->refresh();
    expect($addonRow->enabled)->toBeTrue();

    // Boot cache must include Sample again.
    $cached2 = $cache->read();
    $names2 = array_column($cached2, 'name');
    expect($names2)->toContain('Sample');
});

// ─────────────────────────────────────────────────────────────────────────────
// 10. name/description property access via __get
// ─────────────────────────────────────────────────────────────────────────────

it('$shim->name equals getName()', function (): void {
    $repo = app(ModuleRepository::class);
    $shim = $repo->find('Sample');

    expect($shim)->not->toBeNull()
        ->and($shim->name)->toBe('Sample');
});

it('$shim->description returns null when manifest description is blank', function (): void {
    $repo = app(ModuleRepository::class);
    $shim = $repo->find('Sample');

    // Sample module.json has description: "" which normalises to null.
    expect($shim)->not->toBeNull()
        ->and($shim->description)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// 11. getStudlyName() returns StudlyCase of the module name
// ─────────────────────────────────────────────────────────────────────────────

it('getStudlyName() returns StudlyCase of the module name', function (): void {
    $repo = app(ModuleRepository::class);
    $shim = $repo->find('Sample');

    expect($shim)->not->toBeNull()
        ->and($shim->getStudlyName())->toBe('Sample')
        // StudlyCase is always ≥ the lowercase equivalent.
        ->and($shim->getStudlyName())->not->toBe($shim->getLowerName());
});

// ─────────────────────────────────────────────────────────────────────────────
// 12. delete() removes DB row and boot cache no longer lists the addon
// ─────────────────────────────────────────────────────────────────────────────

it('delete() removes the Addon DB row and the boot cache no longer lists it', function (): void {
    // Create a temporary addon directory with a valid module.json so PrimeService
    // can discover it and write it into the boot cache.
    $tmpDir = storage_path('app/addons/ThrowawayTest'.uniqid());
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/module.json', json_encode([
        'name'      => 'ThrowawayTest',
        'alias'     => 'throwawaytest',
        'providers' => [],
    ]));

    // Run PrimeService so the temp addon is discovered, upserted, and cached.
    app(PrimeService::class)->run();

    // Fetch the DB row PrimeService created for the temp addon.
    $throwaway = Addon::query()->where('path', $tmpDir)->firstOrFail();
    $throwawayId = $throwaway->id;

    // Build a shim around it.
    $shim = new ModuleShim($throwaway, app(ManifestParser::class));

    // Confirm it's in the cache before delete.
    $before = app(BootCache::class)->read();
    $pathsBefore = array_column($before, 'path');
    expect($pathsBefore)->toContain($tmpDir);

    // Delete the DB row (also removes the dir's module.json so re-prime won't re-add it).
    unlink($tmpDir.'/module.json');
    $shim->delete();

    // DB row must be gone.
    expect(Addon::query()->where('id', $throwawayId)->first())->toBeNull();

    // Boot cache must no longer list the deleted addon's path.
    $cached = app(BootCache::class)->read();
    $paths = array_column($cached, 'path');
    expect($paths)->not->toContain($tmpDir);

    // Cleanup temp dir.
    rmdir($tmpDir);
});
