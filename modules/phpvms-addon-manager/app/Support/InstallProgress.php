<?php

declare(strict_types=1);

namespace Modules\AddonManager\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed install progress for an addon, keyed by its registry id. The
 * install job writes each phase; the Addons page polls and renders it while a
 * job is active. The durable record of an install is the addons row + the
 * completion notification — this is transient UI state only.
 *
 * @phpstan-type Progress array{status: string, pct: int, message: string}
 */
class InstallProgress
{
    private const int TTL_MINUTES = 15;

    public static function key(string $registryId): string
    {
        return 'addon-install:'.$registryId;
    }

    public static function set(string $registryId, string $status, int $pct, string $message): void
    {
        self::cache()->put(
            self::key($registryId),
            ['status' => $status, 'pct' => $pct, 'message' => $message],
            now()->addMinutes(self::TTL_MINUTES),
        );
    }

    /**
     * @return Progress|null
     */
    public static function get(string $registryId): ?array
    {
        /** @var Progress|null $value */
        $value = self::cache()->get(self::key($registryId));

        return $value;
    }

    /**
     * True while a job is mid-flight (any non-terminal phase).
     */
    public static function isActive(string $registryId): bool
    {
        $progress = self::get($registryId);

        return $progress !== null && !in_array($progress['status'], ['done', 'error'], true);
    }

    public static function clear(string $registryId): void
    {
        self::cache()->forget(self::key($registryId));
    }

    /**
     * Persistent store so progress written by the install job is readable by the
     * later poll requests — the app's non-persistent `array` default would drop
     * it between requests. Mirrors RegistryClient::store().
     */
    private static function store(): ?string
    {
        $configured = config('addon-manager.cache_store');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return config('cache.default') === 'array' ? 'file' : null;
    }

    private static function cache(): Repository
    {
        return Cache::store(self::store());
    }
}
