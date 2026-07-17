<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Spatie\Permission\PermissionRegistrar;

test('tests run against an in-memory cache store', function (): void {
    // Both phpunit.xml files force CACHE_STORE=array so that every test process
    // gets its own cache. Any persistent store (file, database) is shared by all
    // parallel workers, which leaks spatie's cached permission map between
    // workers that each have their own database — surfacing as random
    // "no permission named X" errors and model_has_permissions FK violations.
    expect(config('cache.default'))->toBe('array')
        ->and(app(PermissionRegistrar::class)->getCacheRepository()->getStore())
        ->toBeInstanceOf(ArrayStore::class);
});
