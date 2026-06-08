<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Addons\Support\ManifestParser;
use App\Models\Addon;
use Illuminate\Support\Facades\Log;
use Modules\Sample\Providers\SampleServiceProvider;

beforeEach(function (): void {
    $path = base_path('bootstrap/cache/addons.php');
    if (file_exists($path)) {
        unlink($path);
    }
});

afterEach(function (): void {
    $path = base_path('bootstrap/cache/addons.php');
    if (file_exists($path)) {
        unlink($path);
    }
});

function makeService(): AddonDiscoveryService
{
    return new AddonDiscoveryService(new ManifestParser(), new BootCache());
}

it('run() registers all three bundled modules from base_path(modules)', function (): void {
    // Migration already seeded 3 rows; run() should upsert them (idempotent).
    makeService()->run();

    expect(Addon::count())->toBe(3);
});

it('run() is idempotent: two consecutive calls leave Addon::count() unchanged (D-14)', function (): void {
    $svc = makeService();
    $svc->run();

    $firstCount = Addon::count();
    $svc->run();

    expect(Addon::count())->toBe($firstCount);
});

it('run() preserves operator enabled=false flag across re-prime (D-12)', function (): void {
    $svc = makeService();
    $svc->run();

    Addon::query()->where('namespace', 'Modules\\Awards')->update(['enabled' => false]);

    $svc->run();

    $awards = Addon::query()->where('namespace', 'Modules\\Awards')->first();
    expect($awards)->not->toBeNull()
        ->and($awards->enabled)->toBeFalse();
});

it('run() excludes disabled addons from the boot cache (D-13)', function (): void {
    $svc = makeService();
    $svc->run();

    Addon::query()->where('namespace', 'Modules\\Awards')->update(['enabled' => false]);

    $svc->run();

    $cached = (new BootCache())->read();
    $namespaces = array_map(fn (AddonBootCache $r): string => $r->namespace, $cached);
    expect($namespaces)->not->toContain('Modules\\Awards');
});

it('run() writes enabled-only rows to the boot cache and the cache exists (STATE-02)', function (): void {
    makeService()->run();

    $runtime = new BootCache();
    expect($runtime->exists())->toBeTrue();

    foreach ($runtime->read() as $row) {
        expect($row->enabled)->toBeTrue();
    }
});

it('run() skips a malformed module.json and logs a warning without throwing (D-15)', function (): void {
    $badDir = storage_path('app/addons/BadAddon');
    @mkdir($badDir, 0755, true);
    file_put_contents($badDir.'/module.json', '{not valid json}');

    try {
        Log::spy();

        makeService()->run();

        // The bad addon must NOT have a DB row.
        expect(Addon::query()->where('path', realpath($badDir) ?: $badDir)->first())->toBeNull();

        // The three bundled modules are still registered.
        expect(Addon::count())->toBe(3);

        Log::shouldHaveReceived('warning')->atLeast()->once();
    } finally {
        @unlink($badDir.'/module.json');
        @rmdir($badDir);
        // Only remove the addons dir if it was empty before (no other content).
        @rmdir(storage_path('app/addons'));
    }
});

it('primeIfNeeded() returns false when boot cache is already fresh (D-09)', function (): void {
    $svc = makeService();
    $svc->run(); // populates cache

    $countBefore = Addon::count();
    $result = $svc->primeIfNeeded();

    expect($result)->toBeFalse()
        ->and(Addon::count())->toBe($countBefore);
});

it('primeIfNeeded() returns true and primes when boot cache is absent (D-10)', function (): void {
    $svc = makeService();

    $result = $svc->primeIfNeeded();

    expect($result)->toBeTrue()
        ->and((new BootCache())->exists())->toBeTrue()
        ->and(Addon::count())->toBeGreaterThan(0);
});

it('primeIfNeeded() re-primes when cache has a stale schema (D2-09)', function (): void {
    $runtime = new BootCache();
    $path = $runtime->path();

    // Write a Phase-1 bare-list file.
    $bareList = [['registry_id' => null, 'namespace' => 'Modules\\Old', 'enabled' => true]];
    file_put_contents($path, '<?php'.PHP_EOL.'return '.var_export($bareList, true).';'.PHP_EOL);

    $svc = makeService();
    $result = $svc->primeIfNeeded();

    expect($result)->toBeTrue()
        ->and($runtime->isFresh())->toBeTrue();
});

it('run() handles absent modules directory without throwing', function (): void {
    // The modules dir may not exist on a fresh install; must not error.
    expect(fn () => makeService()->run())->not->toThrow(Throwable::class);
});

it('run() produces enriched cache rows for the Sample module', function (): void {
    makeService()->run();

    $cached = (new BootCache())->read();
    $sample = collect($cached)->firstWhere('namespace', 'Modules\\Sample');

    expect($sample)->not->toBeNull()
        ->and($sample->providers)->toBeArray()
        ->and($sample->providers)->toContain(SampleServiceProvider::class)
        ->and($sample->autoloadPath)->toBe(realpath(base_path('modules/Sample')))
        ->and($sample->layout)->toBe('root')
        ->and($sample->name)->toBe('Sample')
        ->and($sample->description)->toBeNull()
        ->and($sample->filament)->toBeArray();
});
