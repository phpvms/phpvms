<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Features\Addons\Support\AddonSettingsRegistry;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * Reloads settings from their tables and rewrites the package cache with what
 * was read.
 *
 * Needed wherever settings change without `Settings::save()`: migrations and
 * imports writing the tables, and a save that rolled back after the package
 * had already written the uncommitted values to the cache. The package's own
 * `settings:clear-cache` only knows the classes in `config('settings.settings')`,
 * not the addon classes, so this works per class instead: loading a class from
 * its table (not from the cache) is what makes the package cache it.
 */
final readonly class SettingsCacheRefresher
{
    public function __construct(private SettingsRegistry $registry) {}

    /**
     * Reload one instance from its table, replacing its values and its cache
     * entry. Unsaved changes on the instance are discarded.
     */
    public function refresh(Settings $settings): void
    {
        $settings->settingsConfig()->markLoadedFromCache(false);
        $settings->refresh();
    }

    /**
     * Reload every core and addon settings class, and the instances already
     * resolved in this request.
     */
    public function refreshAll(): void
    {
        foreach ([...$this->registry->coreClasses(), ...AddonSettingsRegistry::allClasses()] as $class) {
            try {
                $this->refresh(app()->resolved($class) ? app($class) : new $class());
            } catch (Throwable $throwable) {
                Log::warning('Could not refresh the cached settings of '.$class, ['exception' => $throwable->getMessage()]);
            }
        }
    }
}
