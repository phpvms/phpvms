<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Addons\Support\ManifestParser;

// Boot-cache isolation + cleanup is handled globally in tests/Pest.php, which
// redirects addons.paths.boot_cache to a unique temp file per test. Do not
// touch the real bootstrap/cache/addons.php here.

function makeService(): AddonDiscoveryService
{
    return new AddonDiscoveryService(new ManifestParser(), new BootCache());
}

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
