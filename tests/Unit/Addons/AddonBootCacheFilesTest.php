<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;

it('round-trips the files list through toArray()/fromArray()', function (): void {
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
        filament: [],
        files: ['/modules/Sample/helpers.php'],
    );

    $rehydrated = AddonBootCache::fromArray($row->toArray());

    expect($row->toArray())->toHaveKey('files', ['/modules/Sample/helpers.php'])
        ->and($rehydrated->files)->toBe(['/modules/Sample/helpers.php']);
});

it('defaults files to an empty list when the cache row omits the key', function (): void {
    $rehydrated = AddonBootCache::fromArray([
        'name'          => 'Legacy',
        'namespace'     => 'Modules\\Legacy',
        'path'          => '/modules/Legacy',
        'autoload_path' => '/modules/Legacy',
        'enabled'       => true,
        // no 'files' key — simulates a pre-change cache row
    ]);

    expect($rehydrated->files)->toBe([]);
});

it('filters non-string entries out of a stored files list', function (): void {
    $rehydrated = AddonBootCache::fromArray([
        'name'  => 'Dirty',
        'files' => ['/a/helpers.php', 42, null, '/b/fns.php'],
    ]);

    expect($rehydrated->files)->toBe(['/a/helpers.php', '/b/fns.php']);
});
