<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\Support\BootCache;
use App\Contracts\Service;
use App\Exceptions\SettingNotFound;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\Concerns\CastsSettingValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to per-addon settings stored in `addon_settings`.
 *
 * An addon is addressed by a handle that resolves as its manifest `alias`,
 * its `registry_id`, or its name. Reads are typed via {@see CastsSettingValue}
 * and cached per resolved registry_id + key, so the helper observes the same
 * value regardless of which handle was used.
 *
 * Resolved registry_ids and values are also memoized for the current request,
 * as SettingService does for core settings: a page reading an addon setting in
 * a loop looks each one up once. The service is a singleton registered in
 * config/octane.php 'flush', so Octane discards the memo before every request,
 * and the queue worker clears it before every job.
 */
class AddonSettingService extends Service
{
    use CastsSettingValue;

    /** @var array<string, string|null> Per-request memo of resolved registry_ids, keyed by handle. */
    private array $registryIds = [];

    /** @var array<string, mixed> Per-request memo of values, keyed by "{registry_id}.{formatted key}". */
    private array $memo = [];

    public function __construct(private readonly BootCache $bootCache) {}

    /**
     * Retrieve a typed setting value for the addon identified by $handle.
     *
     * @throws SettingNotFound when the addon or key cannot be resolved.
     */
    public function retrieve(string $handle, string $key): mixed
    {
        $registryId = $this->resolveRegistryId($handle);

        if ($registryId === null) {
            throw new SettingNotFound($handle.' addon not found');
        }

        $formattedKey = AddonSetting::formatKey($key);
        $memoKey = $registryId.'.'.$formattedKey;

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        if (app()->environment('production')) {
            $cache = config('cache.keys.ADDON_SETTINGS');

            return $this->memo[$memoKey] = Cache::remember(
                $cache['key'].$memoKey,
                $cache['time'],
                fn (): mixed => $this->read($registryId, $formattedKey),
            );
        }

        return $this->memo[$memoKey] = $this->read($registryId, $formattedKey);
    }

    /**
     * Forget the memoized registry_ids and values. Called before each queued
     * job, which Octane's flush does not cover, so a long-running worker
     * observes settings changed by other processes.
     */
    public function clearMemo(): void
    {
        $this->registryIds = [];
        $this->memo = [];
    }

    /**
     * Persist a value for the addon identified by $handle and invalidate its
     * cached value. Returns the value on success, or null when the addon/key
     * does not exist (settings are created by the sync, not by store()).
     */
    public function store(string $handle, string $key, mixed $value): mixed
    {
        $registryId = $this->resolveRegistryId($handle);

        if ($registryId === null) {
            return null;
        }

        return $this->storeFor($registryId, $key, $value);
    }

    /**
     * Persist a value for a known addon registry_id. Returns the value on
     * success, or null when the key does not exist. Invalidates the cache.
     */
    public function storeFor(string $registryId, string $key, mixed $value): mixed
    {
        $formattedKey = AddonSetting::formatKey($key);

        $setting = AddonSetting::query()
            ->where('registry_id', $registryId)
            ->where('key', $formattedKey)
            ->first(['id', 'value']);

        if ($setting === null) {
            return null;
        }

        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        if ($value !== null) {
            $setting->value = (string) $value;
            $setting->save();

            $this->forgetCache($registryId, $formattedKey);
            unset($this->memo[$registryId.'.'.$formattedKey]);
        }

        return $value;
    }

    /**
     * Alias for store() that always returns the value.
     */
    public function save(string $handle, string $key, mixed $value): mixed
    {
        return $this->store($handle, $key, $value);
    }

    /**
     * All settings for an addon, ordered for display.
     *
     * @return Collection<int, AddonSetting>
     */
    public function all(string $registryId): Collection
    {
        return AddonSetting::query()
            ->where('registry_id', $registryId)
            ->orderBy('order')
            ->get();
    }

    /**
     * Resolve a handle (alias or registry_id) to its Addon model, or null.
     */
    public function resolveAddon(?string $handle): ?Addon
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        $registryId = $this->resolveRegistryId($handle);

        return $registryId === null ? null : Addon::query()->where('registry_id', $registryId)->first();
    }

    /**
     * Resolve a handle (registry_id, name or manifest alias) to a registry_id.
     *
     * The addons table carries registry_id and name; `alias` lives only in the
     * manifest, so an alias handle is matched against the boot cache.
     */
    public function resolveRegistryId(string $handle): ?string
    {
        if ($handle === '') {
            return null;
        }

        if (array_key_exists($handle, $this->registryIds)) {
            return $this->registryIds[$handle];
        }

        return $this->registryIds[$handle] = $this->findRegistryId($handle);
    }

    private function findRegistryId(string $handle): ?string
    {
        $registryId = Addon::query()->where('registry_id', $handle)->value('registry_id')
            ?? Addon::query()->where('name', $handle)->value('registry_id');

        if ($registryId !== null) {
            return (string) $registryId;
        }

        $entry = $this->bootCache->all()->first(
            fn ($e): bool => $e->alias === $handle
                || $e->registryId === $handle
                || $e->name === $handle
        );

        return $entry?->registryId;
    }

    /**
     * Fetch and cast a single setting value.
     *
     * @throws SettingNotFound when the key has no row for the addon.
     */
    private function read(string $registryId, string $formattedKey): mixed
    {
        $setting = AddonSetting::query()
            ->where('registry_id', $registryId)
            ->where('key', $formattedKey)
            ->first(['type', 'value']);

        if ($setting === null) {
            throw new SettingNotFound($formattedKey.' not found');
        }

        return $this->castSettingValue($setting->type, $setting->value);
    }

    private function forgetCache(string $registryId, string $formattedKey): void
    {
        $cache = config('cache.keys.ADDON_SETTINGS');

        if (is_array($cache) && isset($cache['key'])) {
            Cache::forget($cache['key'].$registryId.'.'.$formattedKey);
        }
    }
}
