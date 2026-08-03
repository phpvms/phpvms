<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Never let addon lifecycle tests write the real bootstrap/cache/addons.php.
        // A unique path per test keeps both parallel runs and sequential tests
        // isolated (a shared path would leak boot-cache state between tests).
        config(['addons.paths.boot_cache' => sys_get_temp_dir().'/phpvms-addons-boot-'.uniqid('', true).'.php']);

        // Same reasoning for the KVP store. It is a single JSON file that
        // Valuestore rewrites wholesale, and more than one test writes it
        // (UtilsTest directly, VersionTest through VersionService), so under
        // --parallel two workers race and one loses its keys. KvpService is not
        // a singleton and reads this config in its constructor, so a unique
        // path per test is enough to isolate them.
        config(['phpvms.kvp_storage_path' => sys_get_temp_dir().'/phpvms-kvp-'.uniqid('', true).'.json']);
    })
    ->afterEach(function (): void {
        // The container is not guaranteed to be booted here. Pest's Tia engine
        // replays cached results and still fires this hook, at which point
        // resolving `config` throws BindingResolutionException and every
        // replayed test is reported as a false failure. Nothing to clean up in
        // that case anyway, since no test body ran.
        if (!app()->bound('config')) {
            return;
        }

        foreach (['addons.paths.boot_cache', 'phpvms.kvp_storage_path'] as $key) {
            $path = config($key);

            if (is_string($path) && str_starts_with($path, sys_get_temp_dir()) && file_exists($path)) {
                @unlink($path);
            }
        }
    })
    ->in('Unit', 'Feature', 'Arch', '../resources/views');
