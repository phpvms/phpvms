<?php

declare(strict_types=1);

use App\Addons\Models\BootCache;

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

it('exists() returns false when cache file is absent', function (): void {
    $cache = new BootCache();

    expect($cache->exists())->toBeFalse();
});

it('exists() returns true after write()', function (): void {
    $cache = new BootCache();
    $cache->write([]);

    expect($cache->exists())->toBeTrue();
});

it('read() returns empty array when cache file is absent', function (): void {
    $cache = new BootCache();

    expect($cache->read())->toBe([]);
});

it('write() then read() round-trips the enabled-addon array', function (): void {
    $cache = new BootCache();
    $addons = [
        [
            'registry_id' => 'acme/widget',
            'namespace'   => 'Modules\\Widget',
            'enabled'     => true,
            'type'        => 'module',
            'version'     => '1.0.0',
            'path'        => 'storage/app/addons/Widget',
        ],
        [
            'registry_id' => null,
            'namespace'   => 'Modules\\Awards',
            'enabled'     => true,
            'type'        => 'module',
            'version'     => null,
            'path'        => 'modules/Awards',
        ],
    ];

    $cache->write($addons);

    expect($cache->read())->toBe($addons);
});

it('written cache file is valid PHP that require returns a versioned wrapper with addons', function (): void {
    $cache = new BootCache();
    $addons = [
        ['registry_id' => 'foo/bar', 'namespace' => 'Modules\\Foo', 'enabled' => true],
    ];

    $cache->write($addons);

    $path = $cache->path();
    expect(file_exists($path))->toBeTrue();

    // php -l syntax check
    exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
    expect($exitCode)->toBe(0);

    // require returns versioned wrapper
    $result = require $path;
    expect($result)->toBeArray()
        ->and($result['schema'])->toBe(BootCache::SCHEMA)
        ->and($result['addons'])->toBe($addons);
});

it('leaves no leftover temp files in bootstrap/cache after write()', function (): void {
    $cache = new BootCache();
    $cache->write([['registry_id' => 'test/a', 'enabled' => true]]);

    $tmpFiles = glob(base_path('bootstrap/cache/addons.php.tmp*')) ?: [];
    expect($tmpFiles)->toBeEmpty();
});

it('delete() removes the file and exists() returns false', function (): void {
    $cache = new BootCache();
    $cache->write([]);

    expect($cache->exists())->toBeTrue();

    $cache->delete();
    expect($cache->exists())->toBeFalse();
});

it('var_export escaping: hostile registry_id value round-trips unchanged', function (): void {
    $cache = new BootCache();
    $hostileId = "'; echo 'pwned"; // SQL/PHP injection attempt
    $addons = [
        [
            'registry_id' => $hostileId,
            'namespace'   => 'Modules\\Evil',
            'enabled'     => true,
        ],
    ];

    $cache->write($addons);
    $result = $cache->read();

    expect($result[0]['registry_id'])->toBe($hostileId);
});

it('path() returns the bootstrap/cache/addons.php path', function (): void {
    $cache = new BootCache();

    expect($cache->path())->toBe(base_path('bootstrap/cache/addons.php'));
});

it('isFresh() returns false when cache file is absent', function (): void {
    $cache = new BootCache();

    expect($cache->isFresh())->toBeFalse();
});

it('isFresh() returns true after write()', function (): void {
    $cache = new BootCache();
    $cache->write([]);

    expect($cache->isFresh())->toBeTrue();
});

it('stale-schema cache: read() returns [] and isFresh() returns false', function (): void {
    $cache = new BootCache();
    $path = $cache->path();

    // Simulate a Phase-1 bare-list cache file (no schema key).
    $bareList = [
        ['registry_id' => 'old/addon', 'namespace' => 'Modules\\Old', 'enabled' => true],
    ];
    $content = '<?php'.PHP_EOL.'return '.var_export($bareList, true).';'.PHP_EOL;
    file_put_contents($path, $content);

    expect($cache->isFresh())->toBeFalse()
        ->and($cache->read())->toBe([]);
});

it('schema-1 wrapper cache: read() returns [] and isFresh() returns false', function (): void {
    $cache = new BootCache();
    $path = $cache->path();

    // Simulate a schema version 1 wrapper.
    $wrapper = [
        'schema' => 1,
        'addons' => [
            ['registry_id' => 'old/addon', 'enabled' => true],
        ],
    ];
    $content = '<?php'.PHP_EOL.'return '.var_export($wrapper, true).';'.PHP_EOL;
    file_put_contents($path, $content);

    expect($cache->isFresh())->toBeFalse()
        ->and($cache->read())->toBe([]);
});
