<?php

declare(strict_types=1);

namespace App\Addons;

use App\Contracts\Service;
use App\Exceptions\AutoloadModeException;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
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
 *   4. For each row: register PSR-4 then register service providers.
 */
class AddonLoader extends Service
{
    public function __construct(
        private readonly AddonRegistry $registry,
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

        if ($rows === []) {
            return;
        }

        $loader ??= $this->classLoader();

        // Guard must run once, before the first addPsr4() call (LOAD-*, D-16).
        $this->guard->assertRuntimeAutoloadSupported($loader);

        foreach ($rows as $row) {
            $this->registerPsr4($loader, $row);
            $this->registerProviders($app, $row);
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
            if (is_array($entry) && isset($entry[0]) && $entry[0] instanceof ClassLoader) {
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
     *
     * @param array<string, mixed> $row Boot-cache row (schema v2).
     */
    private function registerPsr4(ClassLoader $loader, array $row): void
    {
        $namespace = $row['namespace'] ?? '';
        $autoloadPath = $row['autoload_path'] ?? '';

        if ($namespace === '' || $autoloadPath === '') {
            return;
        }

        $prefix = rtrim((string) $namespace, '\\').'\\';

        // Composer tolerates non-existent paths; stale-path rows simply load nothing.
        $loader->addPsr4($prefix, $autoloadPath);
    }

    /**
     * Register all service providers declared by a single addon row.
     *
     * Laravel deduplicates already-registered providers by class, so calling
     * this method multiple times for the same row is idempotent and Octane-safe.
     *
     * @param array<string, mixed> $row Boot-cache row (schema v2).
     */
    private function registerProviders(Application $app, array $row): void
    {
        foreach ($row['providers'] ?? [] as $providerClass) {
            if (!is_string($providerClass)) {
                continue;
            }
            if ($providerClass === '') {
                continue;
            }
            $app->register($providerClass);
        }
    }
}
