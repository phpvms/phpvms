<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

// Boot-cache isolation is handled globally in tests/Pest.php (unique temp path
// per test). This file additionally redirects addons.paths.base to a temp
// directory so it can freely add/remove/edit addon directories.

beforeEach(function (): void {
    Addon::query()->delete();

    $this->base = sys_get_temp_dir().'/addon-disk-fingerprint-'.uniqid('', true);
    File::ensureDirectoryExists($this->base);
    Config::set('addons.paths.base', $this->base);
});

afterEach(function (): void {
    File::deleteDirectory($this->base);
});

/**
 * Drop a valid addon on disk and an enabled DB row for it, mirroring
 * AddonBootCacheReconcileTest's placeEnabledAddon() helper.
 */
function placeAddon(string $base, string $name): void
{
    $dir = $base.'/'.strtolower($name);
    File::ensureDirectoryExists($dir);
    $registryId = 'acme/'.strtolower($name);

    File::put($dir.'/module.json', json_encode(['name' => $name, 'registry_id' => $registryId, 'providers' => []]));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => 'app/']]]));

    Addon::factory()->create([
        'name'        => $name,
        'registry_id' => $registryId,
        'namespace'   => 'Modules\\'.$name,
        'path'        => $dir,
        'enabled'     => true,
    ]);
}

it('rebuilds when a module.json is edited but the DB is untouched', function (): void {
    placeAddon($this->base, 'Alpha');
    $svc = app(AddonDiscoveryService::class);
    $svc->rebuildCache();

    expect($svc->primeIfNeeded())->toBeFalse();

    // Edit the manifest name on disk only — the DB row and its updated_at are
    // untouched, so databaseFingerprint() alone would miss this.
    $manifestPath = $this->base.'/alpha/module.json';
    // Force the mtime forward: some filesystems have 1-second mtime
    // resolution, and this edit can otherwise land in the same second as the
    // original write above.
    File::put($manifestPath, json_encode(['name' => 'AlphaRenamed', 'registry_id' => 'acme/alpha', 'providers' => []]));
    touch($manifestPath, time() + 1);

    expect($svc->primeIfNeeded())->toBeTrue();

    $cached = app(BootCache::class)->enabled()->firstWhere('namespace', 'Modules\\Alpha');
    expect($cached->name)->toBe('AlphaRenamed');
});

it('changes the fingerprint when an addon directory is added', function (): void {
    placeAddon($this->base, 'Alpha');
    $svc = app(AddonDiscoveryService::class);
    $svc->rebuildCache();

    expect($svc->primeIfNeeded())->toBeFalse();

    placeAddon($this->base, 'Beta');

    expect($svc->primeIfNeeded())->toBeTrue()
        ->and(app(BootCache::class)->enabled()->pluck('namespace')->all())
        ->toContain('Modules\\Alpha', 'Modules\\Beta');
});

it('changes the fingerprint when an addon directory is removed', function (): void {
    placeAddon($this->base, 'Alpha');
    placeAddon($this->base, 'Beta');
    $svc = app(AddonDiscoveryService::class);
    $svc->rebuildCache();

    expect($svc->primeIfNeeded())->toBeFalse();

    File::deleteDirectory($this->base.'/beta');
    Addon::where('namespace', 'Modules\\Beta')->delete();

    expect($svc->primeIfNeeded())->toBeTrue()
        ->and(app(BootCache::class)->enabled()->pluck('namespace')->all())
        ->toBe(['Modules\\Alpha']);
});

it('does not rewrite the cache file when disk and DB are both unchanged', function (): void {
    placeAddon($this->base, 'Alpha');
    $svc = app(AddonDiscoveryService::class);
    $svc->rebuildCache();

    $path = app(BootCache::class)->path();
    $mtimeBefore = filemtime($path);

    // Filesystem mtime resolution can be coarse; sleeping a full second makes
    // a spurious rewrite observable regardless of resolution.
    sleep(1);

    expect($svc->primeIfNeeded())->toBeFalse()
        ->and(filemtime($path))->toBe($mtimeBefore);
});

it('treats a schema-3 cache as stale and rebuilds it', function (): void {
    placeAddon($this->base, 'Alpha');

    $runtime = app(BootCache::class);
    $legacyEnvelope = [
        'schema'       => 3,
        'generated_at' => gmdate('c'),
        'fingerprint'  => null,
        'addons'       => [],
    ];
    file_put_contents(
        $runtime->path(),
        '<?php'.PHP_EOL.'return '.var_export($legacyEnvelope, true).';'.PHP_EOL,
    );

    $svc = app(AddonDiscoveryService::class);

    expect($runtime->isFresh())->toBeFalse()
        ->and($svc->primeIfNeeded())->toBeTrue()
        ->and($runtime->isFresh())->toBeTrue();
});
