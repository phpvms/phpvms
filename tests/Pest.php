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
        $bootCache = sys_get_temp_dir().'/phpvms-addons-boot-'.uniqid('', true).'.php';

        // Same reasoning for the KVP store. It is a single JSON file that
        // Valuestore rewrites wholesale, and more than one test writes it
        // (UtilsTest directly, VersionTest through VersionService), so under
        // --parallel two workers race and one loses its keys. KvpService is not
        // a singleton and reads this config in its constructor, so a unique
        // path per test is enough to isolate them.
        $kvpStore = sys_get_temp_dir().'/phpvms-kvp-'.uniqid('', true).'.json';

        config([
            'addons.paths.boot_cache' => $bootCache,
            'phpvms.kvp_storage_path' => $kvpStore,
        ]);

        // Recorded for cleanup so afterEach never has to read these back out of
        // the container. Doing so there is not safe: Pest's Tia engine can reach
        // afterEach after Laravel has torn the application down, at which point
        // the Application object still exists but its bindings are flushed, so
        // app('config') resolves to the Facade class and config() dies with
        // "Call to undefined method Illuminate\Support\Facades\Config::get()".
        phpvmsTempPaths([$bootCache, $kvpStore]);
    })
    ->afterEach(function (): void {
        // Pure filesystem work -- no container access, so this is safe whatever
        // state the application is in, including a replayed test that never ran.
        foreach (phpvmsTempPaths() as $path) {
            if (str_starts_with($path, sys_get_temp_dir()) && file_exists($path)) {
                @unlink($path);
            }
        }
    })
    ->in('Unit', 'Feature', 'Arch', '../resources/views');
