<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use Illuminate\Support\Collection;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a minimal AddonBootCache object for testing.
 *
 * @param array<string, mixed> $overrides
 */
function makeAddonBootCache(array $overrides = []): AddonBootCache
{
    return AddonBootCache::fromArray(array_merge([
        'name'          => 'TestAddon',
        'alias'         => 'testaddon',
        'type'          => 'module',
        'registry_id'   => 'acme/test',
        'version'       => '1.0.0',
        'namespace'     => 'Modules\\TestAddon',
        'providers'     => [],
        'path'          => '/var/addons/TestAddon',
        'autoload_path' => '/var/addons/TestAddon',
        'layout'        => 'app',
        'description'   => null,
        'enabled'       => true,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Setup / teardown
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    $path = base_path('bootstrap/cache/addons.php');
    if (file_exists($path)) {
        unlink($path);
    }

    foreach (glob(base_path('bootstrap/cache/addons.php.tmp*')) ?: [] as $tmp) {
        @unlink($tmp);
    }
});

afterEach(function (): void {
    $path = base_path('bootstrap/cache/addons.php');
    if (file_exists($path)) {
        unlink($path);
    }

    foreach (glob(base_path('bootstrap/cache/addons.php.tmp*')) ?: [] as $tmp) {
        @unlink($tmp);
    }
});

// ---------------------------------------------------------------------------
// write() / read() round-trips
// ---------------------------------------------------------------------------

it('write() then read() round-trips AddonBootCache objects', function (): void {
    $runtime = new BootCache();

    $addon1 = makeAddonBootCache([
        'name'        => 'Widget',
        'namespace'   => 'Modules\\Widget',
        'registry_id' => 'acme/widget',
        'enabled'     => true,
        'providers'   => ['Modules\\Widget\\Providers\\WidgetServiceProvider'],
    ]);

    $addon2 = makeAddonBootCache([
        'name'        => 'Awards',
        'namespace'   => 'Modules\\Awards',
        'registry_id' => null,
        'version'     => null,
        'enabled'     => true,
    ]);

    $runtime->write([$addon1, $addon2]);

    $result = $runtime->read();

    expect($result)->toHaveCount(2)
        ->and($result[0]->toArray())->toBe($addon1->toArray())
        ->and($result[1]->toArray())->toBe($addon2->toArray());
});

it('read() returns empty array when cache file is absent', function (): void {
    $runtime = new BootCache();

    expect($runtime->read())->toBe([]);
});

// ---------------------------------------------------------------------------
// isFresh()
// ---------------------------------------------------------------------------

it('isFresh() returns true after a real write()', function (): void {
    $runtime = new BootCache();
    $runtime->write([]);

    expect($runtime->isFresh())->toBeTrue();
});

it('isFresh() returns false when cache file is absent', function (): void {
    $runtime = new BootCache();

    expect($runtime->isFresh())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Stale-schema handling
// ---------------------------------------------------------------------------

it('bare-list cache (no schema key): read() returns [] and isFresh() returns false', function (): void {
    $runtime = new BootCache();
    $path = $runtime->path();

    $bareList = [
        ['registry_id' => 'old/addon', 'namespace' => 'Modules\\Old', 'enabled' => true],
    ];
    $content = '<?php'.PHP_EOL.'return '.var_export($bareList, true).';'.PHP_EOL;
    file_put_contents($path, $content);

    expect($runtime->isFresh())->toBeFalse()
        ->and($runtime->read())->toBe([]);
});

it('schema-1 wrapper cache: read() returns [] and isFresh() returns false', function (): void {
    $runtime = new BootCache();
    $path = $runtime->path();

    $wrapper = [
        'schema' => 1,
        'addons' => [
            ['registry_id' => 'old/addon', 'enabled' => true],
        ],
    ];
    $content = '<?php'.PHP_EOL.'return '.var_export($wrapper, true).';'.PHP_EOL;
    file_put_contents($path, $content);

    expect($runtime->isFresh())->toBeFalse()
        ->and($runtime->read())->toBe([]);
});

// ---------------------------------------------------------------------------
// delete()
// ---------------------------------------------------------------------------

it('delete() removes the file', function (): void {
    $runtime = new BootCache();
    $runtime->write([]);

    expect($runtime->exists())->toBeTrue();

    $runtime->delete();

    expect($runtime->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Security: hostile values round-trip unchanged
// ---------------------------------------------------------------------------

it('hostile registryId round-trips unchanged through write()/read()', function (): void {
    $runtime = new BootCache();
    $hostileId = "'; echo 'pwned";

    $addon = makeAddonBootCache(['registry_id' => $hostileId]);
    $runtime->write([$addon]);

    $result = $runtime->read();

    expect($result[0]->registryId)->toBe($hostileId);
});

// ---------------------------------------------------------------------------
// No leftover temp files
// ---------------------------------------------------------------------------

it('leaves no leftover temp files in bootstrap/cache after write()', function (): void {
    $runtime = new BootCache();
    $runtime->write([makeAddonBootCache()]);

    $tmpFiles = glob(base_path('bootstrap/cache/addons.php.tmp*')) ?: [];

    expect($tmpFiles)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// enabled() filtering
// ---------------------------------------------------------------------------

it('enabled() returns only enabled rows as a Collection', function (): void {
    $runtime = new BootCache();

    $enabledAddon = makeAddonBootCache(['name' => 'Enabled',  'namespace' => 'Modules\\Enabled',  'enabled' => true]);
    $disabledAddon = makeAddonBootCache(['name' => 'Disabled', 'namespace' => 'Modules\\Disabled', 'enabled' => false]);

    $runtime->write([$enabledAddon, $disabledAddon]);

    $result = $runtime->enabled();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(1)
        ->and($result->first()->name)->toBe('Enabled');
});

it('enabled() returns empty Collection when cache is absent', function (): void {
    $runtime = new BootCache();

    $result = $runtime->enabled();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// all()
// ---------------------------------------------------------------------------

it('all() returns a Collection of every row', function (): void {
    $runtime = new BootCache();

    $addon1 = makeAddonBootCache(['name' => 'Alpha', 'namespace' => 'Modules\\Alpha', 'enabled' => true]);
    $addon2 = makeAddonBootCache(['name' => 'Beta',  'namespace' => 'Modules\\Beta',  'enabled' => false]);

    $runtime->write([$addon1, $addon2]);

    $result = $runtime->all();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(2);
});

it('all() returns empty Collection when cache is absent', function (): void {
    $runtime = new BootCache();

    $result = $runtime->all();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toBeEmpty();
});
