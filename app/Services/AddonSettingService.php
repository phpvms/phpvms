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
 * An addon is addressed by a handle that resolves as either its manifest
 * `alias` or `registry_id` (alias is universal; bundled addons have a null
 * registry_id). Reads are typed via {@see CastsSettingValue} and cached per
 * resolved addon + key, so the helper observes the same value regardless of
 * which handle was used.
 *
 * Stateless and Octane-safe: no mutable instance state; all caching lives in
 * the application cache.
 */
class AddonSettingService extends Service
{
    use CastsSettingValue;

    public function __construct(private readonly BootCache $bootCache) {}

    /**
     * Retrieve a typed setting value for the addon identified by $handle.
     *
     * @throws SettingNotFound when the addon or key cannot be resolved.
     */
    public function retrieve(string $handle, string $key): mixed
    {
        $addonId = $this->resolveAddonId($handle);

        if ($addonId === null) {
            throw new SettingNotFound($handle.' addon not found');
        }

        $formattedKey = AddonSetting::formatKey($key);

        if (app()->environment('production')) {
            $cache = config('cache.keys.ADDON_SETTINGS');

            return Cache::remember(
                $cache['key'].$addonId.'.'.$formattedKey,
                $cache['time'],
                fn (): mixed => $this->read($addonId, $formattedKey),
            );
        }

        return $this->read($addonId, $formattedKey);
    }

    /**
     * Persist a value for the addon identified by $handle and invalidate its
     * cached value. Returns the value on success, or null when the addon/key
     * does not exist (settings are created by the sync, not by store()).
     */
    public function store(string $handle, string $key, mixed $value): mixed
    {
        $addonId = $this->resolveAddonId($handle);

        if ($addonId === null) {
            return null;
        }

        return $this->storeById($addonId, $key, $value);
    }

    /**
     * Persist a value for a known addon id. Returns the value on success, or
     * null when the key does not exist. Invalidates the cached value.
     */
    public function storeById(int $addonId, string $key, mixed $value): mixed
    {
        $formattedKey = AddonSetting::formatKey($key);

        $setting = AddonSetting::query()
            ->where('addon_id', $addonId)
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

            $this->forgetCache($addonId, $formattedKey);
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
    public function all(int $addonId): Collection
    {
        return AddonSetting::query()
            ->where('addon_id', $addonId)
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

        $addonId = $this->resolveAddonId($handle);

        return $addonId === null ? null : Addon::query()->find($addonId);
    }

    /**
     * Resolve a handle to an addon id.
     *
     * Tries the addons table first (registry_id, then name), then falls back to
     * the boot cache for the manifest `alias` — which is not stored on the
     * addons table — mapping the matched entry back to its addon row.
     */
    public function resolveAddonId(string $handle): ?int
    {
        if ($handle === '') {
            return null;
        }

        $id = Addon::query()->where('registry_id', $handle)->value('id')
            ?? Addon::query()->where('name', $handle)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $entry = $this->bootCache->all()->first(
            fn ($e): bool => $e->alias === $handle
                || $e->registryId === $handle
                || $e->name === $handle
        );

        if ($entry === null) {
            return null;
        }

        $query = Addon::query();

        if ($entry->registryId !== null) {
            $query->where('registry_id', $entry->registryId);
        } else {
            $query->where('namespace', $entry->namespace);
        }

        $resolved = $query->value('id');

        return $resolved === null ? null : (int) $resolved;
    }

    /**
     * Fetch and cast a single setting value.
     *
     * @throws SettingNotFound when the key has no row for the addon.
     */
    private function read(int $addonId, string $formattedKey): mixed
    {
        $setting = AddonSetting::query()
            ->where('addon_id', $addonId)
            ->where('key', $formattedKey)
            ->first(['type', 'value']);

        if ($setting === null) {
            throw new SettingNotFound($formattedKey.' not found');
        }

        return $this->castSettingValue($setting->type, $setting->value);
    }

    private function forgetCache(int $addonId, string $formattedKey): void
    {
        $cache = config('cache.keys.ADDON_SETTINGS');

        if (is_array($cache) && isset($cache['key'])) {
            Cache::forget($cache['key'].$addonId.'.'.$formattedKey);
        }
    }
}
