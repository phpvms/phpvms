<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\BootCache;
use App\Models\Addon;

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

it('enabled() returns what BootCache::read() returns when cache is written', function (): void {
    $cache = new BootCache();
    $addons = [
        [
            'registry_id' => 'acme/widget',
            'namespace'   => 'Modules\\Widget',
            'enabled'     => true,
            'type'        => 'module',
            'version'     => '1.0.0',
            'path'        => '/var/addons/Widget',
        ],
    ];
    $cache->write($addons);

    $registry = new AddonRegistry($cache);

    expect($registry->enabled())->toBe($addons);
});

it('enabled() returns empty array when cache is absent', function (): void {
    $cache = new BootCache();
    $registry = new AddonRegistry($cache);

    expect($registry->enabled())->toBe([]);
});

it('all() returns all Addon rows including disabled ones', function (): void {
    Addon::factory()->create(['enabled' => true]);
    Addon::factory()->create(['enabled' => false]);

    $total = Addon::count();
    $registry = new AddonRegistry(new BootCache());

    expect($registry->all()->count())->toBe($total);
});

it('find() resolves by registry_id', function (): void {
    $addon = Addon::factory()->create(['registry_id' => 'acme/widget']);
    $registry = new AddonRegistry(new BootCache());

    $found = $registry->find('acme/widget');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($addon->id);
});

it('find() resolves by path', function (): void {
    // Use a path that won't conflict with the migration-seeded bundled modules.
    $path = base_path('modules/TestOnlyWidget_'.uniqid());
    $addon = Addon::factory()->create(['registry_id' => null, 'path' => $path]);
    $registry = new AddonRegistry(new BootCache());

    $found = $registry->find($path);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($addon->id);
});

it('find() returns null when no match is found', function (): void {
    $registry = new AddonRegistry(new BootCache());

    expect($registry->find('nope/nothing'))->toBeNull();
});
