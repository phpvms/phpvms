<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\AutoloadGuard;
use App\Addons\Support\BootCache;
use App\Exceptions\AutoloadModeException;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use RuntimeException;

/**
 * Runtime PSR-4 + service-provider registration for enabled addons.
 *
 * Called once per worker/boot cycle (after the boot cache has been primed)
 * to register each enabled addon's namespace with Composer's ClassLoader and
 * boot its declared service providers.
 *
 * Stateless and Octane-safe: no mutable instance properties. The same instance
 * may be reused across requests without side-effects — all state lives in
 * Composer's global loader and Laravel's provider registry.
 *
 * Ordering invariant (LOAD-*):
 *   1. Resolve enabled addon rows from the boot cache (DB-free hot path).
 *   2. Capture Composer's ClassLoader from the autoload stack.
 *   3. Assert classmap-authoritative guard ONCE, BEFORE any addPsr4() call.
 *   4. For each row: register PSR-4, require autoload.files, then register
 *      service providers. Files load after PSR-4 so a module helpers.php may
 *      reference the addon's own classes; they load before providers so a
 *      provider can rely on those global helpers.
 */
class AddonAutoLoader
{
    public function __construct(
        private readonly BootCache $registry,
        private readonly AutoloadGuard $guard,
    ) {}

    /**
     * Register all enabled addons: PSR-4 namespaces + service providers.
     *
     * Accepts an optional $loader for testing (avoids polluting the real autoloader).
     *
     * @throws AutoloadModeException when classmap-authoritative mode detected
     * @throws RuntimeException      when the Composer ClassLoader cannot be found
     */
    public function register(Application $app, ?ClassLoader $loader = null): void
    {
        $rows = $this->registry->enabled();

        if ($rows->isEmpty()) {
            return;
        }

        $loader ??= $this->classLoader();

        // Guard must run once, before the first addPsr4() call (LOAD-*, D-16).
        $this->guard->assertRuntimeAutoloadSupported($loader);

        foreach ($rows as $entry) {
            // Missing on disk (deleted files / broken symlink): don't load a
            // phantom addon. A stale boot-cache row with a vanished path loads
            // nothing useful and would otherwise emit a misleading
            // "not a ServiceProvider" warning when its provider class fails to
            // autoload.
            if ($entry->autoloadPath !== '' && !is_dir($entry->autoloadPath)) {
                Log::warning(sprintf(
                    'AddonAutoLoader: skipping addon "%s" — autoload path missing on disk: %s',
                    $entry->namespace,
                    $entry->autoloadPath,
                ));

                continue;
            }

            $this->registerPsr4($loader, $entry);
            $this->loadAutoloadFiles($entry);
            $this->registerProviders($app, $entry);
        }
    }

    /**
     * Return the Composer ClassLoader instance from the global autoload stack.
     *
     * Composer registers its loader as [$classLoaderInstance, 'loadClass'].
     *
     * @throws RuntimeException when no ClassLoader is found in the autoload stack
     */
    public function classLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $entry) {
            if (is_array($entry) && $entry[0] instanceof ClassLoader) {
                return $entry[0];
            }
        }

        throw new RuntimeException('Composer ClassLoader not found in autoload stack');
    }

    /**
     * Register a single addon's PSR-4 namespace with the ClassLoader.
     *
     * Normalises the namespace prefix to end with exactly one trailing backslash.
     * Uses addPsr4() (not prependPsr4()) so core mappings retain precedence (PITFALLS #7).
     */
    private function registerPsr4(ClassLoader $loader, AddonBootCache $entry): void
    {
        if ($entry->namespace === '' || $entry->autoloadPath === '') {
            return;
        }

        $prefix = rtrim($entry->namespace, '\\').'\\';

        // Composer tolerates non-existent paths; stale-path rows simply load nothing.
        $loader->addPsr4($prefix, $entry->autoloadPath);
    }

    /**
     * Require each composer `autoload.files` entry declared by the addon.
     *
     * Mirrors the root project's `app/helpers.php` autoload for modules: global
     * functions defined in a module's helpers file become available at runtime.
     *
     * Uses require_once guarded by is_file() so repeated registration in the
     * same process (Octane worker reuse) cannot "cannot redeclare function" —
     * and stale paths are skipped, consistent with registerPsr4().
     */
    private function loadAutoloadFiles(AddonBootCache $entry): void
    {
        foreach ($entry->files as $file) {
            if ($file === '') {
                continue;
            }

            if (!is_file($file)) {
                continue;
            }

            require_once $file;
        }
    }

    /**
     * Register all service providers declared by a single addon entry.
     *
     * Each declared class is validated to be a genuine ServiceProvider before
     * registration — a manifest is addon-controlled input, so a crafted
     * `providers` entry must never be able to instantiate an arbitrary class.
     *
     * Laravel deduplicates already-registered providers by class, so calling
     * this method multiple times for the same entry is idempotent and Octane-safe.
     */
    private function registerProviders(Application $app, AddonBootCache $entry): void
    {
        foreach ($entry->providers as $providerClass) {
            if ($providerClass === '') {
                continue;
            }

            if (!is_a($providerClass, IlluminateServiceProvider::class, true)) {
                Log::warning(sprintf(
                    'AddonAutoLoader: skipping provider "%s" for addon "%s" — not a ServiceProvider subclass.',
                    $providerClass,
                    $entry->namespace,
                ));

                continue;
            }

            $app->register($providerClass);
        }
    }
}
