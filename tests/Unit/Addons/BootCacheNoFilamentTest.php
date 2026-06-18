<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;

it('no longer serializes a filament field in boot-cache rows', function (): void {
    $row = new AddonBootCache(
        name: 'Sample',
        alias: 'sample',
        type: 'module',
        registryId: null,
        version: '1.0.0',
        namespace: 'Modules\\Sample',
        providers: [],
        path: '/modules/Sample',
        autoloadPath: '/modules/Sample',
        layout: 'root',
        description: null,
        enabled: true,
    );

    expect($row->toArray())->not->toHaveKey('filament');
});

it('ignores a stale filament key when hydrating an old cache row', function (): void {
    $row = AddonBootCache::fromArray([
        'name'     => 'Legacy',
        'filament' => ['admin' => ['Resources' => '/x']],
    ]);

    expect($row)->toBeInstanceOf(AddonBootCache::class)
        ->and($row->toArray())->not->toHaveKey('filament');
});

it('bumped the boot-cache schema so pre-change caches rebuild', function (): void {
    expect(BootCache::SCHEMA)->toBeGreaterThanOrEqual(3);
});
