<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\SettingNotFound;
use App\Models\Setting;
use App\Services\Concerns\CastsSettingValue;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to application settings stored in the `settings` table.
 *
 * Replaces the deleted SettingRepository. Owns:
 *   - retrieve(): typed read of a single setting (throws SettingNotFound on miss)
 *   - store():    update an existing setting; ALSO invalidates the per-key
 *                 cache slot under `cache.keys.SETTINGS` so the `setting()`
 *                 global helper observes the new value on the next read
 *   - save():     alias for store() that returns the value (mirrors the deleted repo's API)
 *
 * The 60-second CacheableRepository layer that the old repo inherited is
 * intentionally not preserved — the only path doing many reads is the
 * `setting()` helper, which has its own application cache.
 *
 * Tier-1 memo: $memo caches resolved values for the duration of one request
 * (keyed by the formatted setting id). The service is bound as a singleton
 * and registered in config/octane.php 'flush' so Octane discards the instance
 * (and its memo) before every new request.
 */
class SettingService extends Service
{
    use CastsSettingValue;

    /** @var array<string, mixed> Per-request in-process memo, keyed by formatted setting id. */
    private array $memo = [];

    /**
     * Retrieve a typed setting value.
     *
     * Resolved values are memoized in $memo for the lifetime of the current
     * request so repeated reads of the same key hit the source at most once.
     *
     * @throws SettingNotFound when the key has no row in `settings`.
     */
    public function retrieve(string $key): mixed
    {
        $key = Setting::formatKey($key);

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $setting = Setting::where('id', $key)->first(['type', 'value']);

        if ($setting === null) {
            throw new SettingNotFound($key.' not found');
        }

        return $this->memo[$key] = $this->castSettingValue($setting->type, $setting->value);
    }

    /**
     * Update an existing setting. Returns the value on success, null when
     * the key does not exist OR when the caller passes a null $value
     * (does not insert new settings — these are seeded by migrations).
     *
     * Invalidates the per-key application cache slot on success so the
     * `setting()` global helper observes the new value. The cache slot
     * is keyed by the ORIGINAL caller-supplied key (not the formatted
     * one), matching the behavior of the deleted Filament page's inline
     * Cache::forget — see `app/helpers.php` Cache::remember call which
     * also caches under the original key.
     */
    public function store(string $key, mixed $value): mixed
    {
        $formattedKey = Setting::formatKey($key);

        $setting = Setting::where('id', $formattedKey)->first(['id', 'value']);

        if ($setting === null) {
            return null;
        }

        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        if ($value !== null) {
            $setting->value = (string) $value;
            $setting->save();

            $this->forgetCache($key);
            unset($this->memo[$formattedKey]);
        }

        return $value;
    }

    /**
     * Alias for store(). Mirrors the deleted SettingRepository::save().
     */
    public function save(string $key, mixed $value): mixed
    {
        return $this->store($key, $value);
    }

    /**
     * Clear all memoized values. Called by AppServiceProvider's DB listener
     * when any write to the settings table occurs (covering paths that bypass
     * store(), such as YamlDatabaseService raw DB writes).
     */
    public function clearMemo(): void
    {
        $this->memo = [];
    }

    private function forgetCache(string $key): void
    {
        $cache = config('cache.keys.SETTINGS');
        if (is_array($cache) && isset($cache['key'])) {
            Cache::forget($cache['key'].$key);
        }
    }
}
