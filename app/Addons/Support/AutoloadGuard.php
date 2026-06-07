<?php

declare(strict_types=1);

namespace App\Addons;

use App\Exceptions\AutoloadModeException;
use Composer\Autoload\ClassLoader;

/**
 * Detects classmap-authoritative autoload mode and throws an actionable
 * exception when runtime PSR-4 registration would silently fail (LOAD-08,
 * D-16, T-03-04).
 *
 * Stateless — inject a ClassLoader explicitly in tests to avoid touching
 * the global registered-loaders list.
 *
 * NOTE: import is \Composer\Autoload\ClassLoader — NOT App\Support\ClassLoader
 * (the local class-map helper that happens to share the short name).
 */
class AutoloadGuard
{
    /**
     * Return true if the given (or globally registered) ClassLoader is
     * running in classmap-authoritative mode.
     */
    public function isClassMapAuthoritative(?ClassLoader $loader = null): bool
    {
        $loader ??= $this->resolveRegisteredLoader();

        if (!$loader instanceof ClassLoader) {
            return false;
        }

        return $loader->isClassMapAuthoritative();
    }

    /**
     * Throw AutoloadModeException when the autoloader is in classmap-authoritative
     * mode. Returns void (no-op) when safe.
     *
     * @throws AutoloadModeException
     */
    public function assertRuntimeAutoloadSupported(?ClassLoader $loader = null): void
    {
        if ($this->isClassMapAuthoritative($loader)) {
            throw new AutoloadModeException();
        }
    }

    /**
     * Resolve the globally registered Composer ClassLoader.
     * Prefers the loader registered for base_path('vendor'); falls back to
     * the first registered loader if that key is absent.
     */
    private function resolveRegisteredLoader(): ?ClassLoader
    {
        $loaders = ClassLoader::getRegisteredLoaders();

        if ($loaders === []) {
            return null;
        }

        $vendorDir = base_path('vendor');

        return $loaders[$vendorDir] ?? array_values($loaders)[0];
    }
}
