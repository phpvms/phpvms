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

/*
|--------------------------------------------------------------------------
| Test Impact Analysis
|--------------------------------------------------------------------------
|
| Replays cached results for tests unaffected by local changes (~27s -> ~4s).
| Needs a coverage driver (pcov or xdebug); the graph is stored outside the
| repository, in ~/.pest/tia/<project-key>/.
|
| `locally()` rather than `always()` on purpose: it is the only mode Pest
| disables automatically when `--ci` is present, so CI always runs the real
| suite. Note that an explicit `--tia` on the command line bypasses that
| guard, which is why it must never be added to the CI command -- CI runs
| `pest --ci --parallel --bail` and stays unaffected.
|
| Use `--no-tia` to force a full local run.
|
*/
pest()->tia()->locally();

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

        // Same reasoning again for the writable addon root. install()/update()
        // place files there for real, so leaving it at its configured default
        // (storage/addons) means a lifecycle test writes an addon directory
        // into the working copy and the NEXT run fails on "Addon already
        // installed". A test that cares about the image/installed split points
        // this at its own fixture; everything else just needs it off the repo.
        $installedAddons = sys_get_temp_dir().'/phpvms-addons-installed-'.uniqid('', true);

        // And for addons of type "theme", which install into their own root
        // (storage/addon-themes) and link their public/ into the public disk
        // (storage/app/public/themes). Both are written for real by install().
        $installedThemes = sys_get_temp_dir().'/phpvms-addon-themes-'.uniqid('', true);
        $themeAssets = sys_get_temp_dir().'/phpvms-theme-assets-'.uniqid('', true);

        config([
            'addons.paths.boot_cache'   => $bootCache,
            'addons.paths.installed'    => $installedAddons,
            'addons.paths.themes'       => $installedThemes,
            'addons.paths.theme_assets' => $themeAssets,
            'phpvms.kvp_storage_path'   => $kvpStore,
        ]);

        // Pin the registry cache to the (array) test store at runtime so a stale
        // bootstrap/cache/config.php can't override the phpunit env and make
        // RegistryClient fall back to the `file` store — which is shared with a
        // dev app and would leak fixture catalogs into it. Runtime config() wins
        // over cached config.
        config(['addon-manager.cache_store' => 'array']);

        // Recorded for cleanup so afterEach never has to read these back out of
        // the container. Doing so there is not safe: Pest's Tia engine can reach
        // afterEach after Laravel has torn the application down, at which point
        // the Application object still exists but its bindings are flushed, so
        // app('config') resolves to the Facade class and config() dies with
        // "Call to undefined method Illuminate\Support\Facades\Config::get()".
        phpvmsTempPaths([$bootCache, $kvpStore, $installedAddons, $installedThemes, $themeAssets]);
    })
    ->afterEach(function (): void {
        // Pure filesystem work -- no container access, so this is safe whatever
        // state the application is in, including a replayed test that never ran.
        foreach (phpvmsTempPaths() as $path) {
            if (!str_starts_with($path, sys_get_temp_dir()) || !file_exists($path)) {
                continue;
            }

            // The installed addon root is a directory tree, not a single file.
            if (is_dir($path)) {
                phpvmsDeleteTempTree($path);

                continue;
            }

            @unlink($path);
        }
    })
    ->in('Unit', 'Feature', 'Arch', '../resources/views');
