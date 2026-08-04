<?php

declare(strict_types=1);

namespace Modules\AddonManager\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The persistent cache store for addon-manager data (catalog, release
 * metadata, install progress, update-notification markers).
 *
 * These values MUST survive across requests — the whole point of the catalog
 * cache is to avoid a network fetch on every page interaction. Honours an
 * explicit config; else the app default — unless that is the non-persistent
 * `array` driver (dev), in which case `file` (storage/framework/cache) is used
 * so it still persists. Mirrored by InstallProgress, RegistryClient and
 * CheckUpdates via this single resolution point.
 */
final class RegistryCache
{
    /**
     * The resolved cache repository.
     */
    public static function store(): Repository
    {
        return Cache::store(self::storeName());
    }

    /**
     * The resolved store name, or null for the app default.
     */
    public static function storeName(): ?string
    {
        $configured = config('addon-manager.cache_store');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return config('cache.default') === 'array' ? 'file' : null;
    }
}
