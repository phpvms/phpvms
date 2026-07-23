<?php

declare(strict_types=1);

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    Addon::query()->delete();

    $this->base = sys_get_temp_dir().'/addon-reconcile-'.uniqid('', true);
    File::ensureDirectoryExists($this->base);
    Config::set('addons.paths.base', $this->base);
});

afterEach(function (): void {
    File::deleteDirectory($this->base);
});

/**
 * Drop a valid addon on disk and create a matching enabled DB row so rebuildCache
 * projects it into the boot cache.
 */
function placeEnabledAddon(string $base, string $name): void
{
    $dir = $base.'/'.strtolower($name);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode(['name' => $name, 'providers' => []]));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.$name.'\\' => 'app/']]]));

    Addon::factory()->create([
        'name'      => $name,
        'namespace' => 'Modules\\'.$name,
        'path'      => $dir,
        'enabled'   => true,
    ]);
}

it('does not rebuild when the cache already matches the DB addons state', function (): void {
    placeEnabledAddon($this->base, 'Alpha');
    $svc = app(AddonDiscoveryService::class);
    $svc->rebuildCache();

    // Cache was just built from the current DB — nothing to reconcile.
    expect($svc->primeIfNeeded())->toBeFalse()
        ->and(app(BootCache::class)->enabled()->pluck('namespace')->all())->toBe(['Modules\\Alpha']);
});

it('rebuilds when an addon is enabled in the DB after the cache was written', function (): void {
    placeEnabledAddon($this->base, 'Alpha');
    $svc = app(AddonDiscoveryService::class);
    $svc->rebuildCache();

    expect($svc->primeIfNeeded())->toBeFalse();

    // A second addon becomes enabled in the DB — the schema-valid cache is now
    // data-stale. isFresh() would still say "fresh"; the fingerprint check must
    // catch the divergence and rebuild.
    placeEnabledAddon($this->base, 'Beta');

    expect($svc->primeIfNeeded())->toBeTrue()
        ->and(app(BootCache::class)->enabled()->pluck('namespace')->all())
        ->toContain('Modules\\Alpha', 'Modules\\Beta');
});
