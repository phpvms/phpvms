<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\AddonSettingService;
use App\Services\SettingService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;

/*
 * In production, setting() and addon_setting() read through the application
 * cache. The Redis store keeps a bare number unserialized and hands it back as
 * a string, so an int setting read from a warm cache came back as "250" and
 * broke typed callers, e.g. PirepFinanceService::getGroundHandlingCost(): ?float
 * returning "250" for an airport without its own ground handling cost.
 *
 * The array store here does the same: it stores numbers as strings.
 */
beforeEach(function (): void {
    Cache::extend('numbers-as-strings', fn () => Cache::repository(new class() extends ArrayStore
    {
        public function put($key, $value, $seconds)
        {
            return parent::put($key, is_numeric($value) ? (string) $value : $value, $seconds);
        }
    }));
    config([
        'cache.stores.numbers-as-strings' => ['driver' => 'numbers-as-strings'],
        'cache.default'                   => 'numbers-as-strings',
    ]);
    Cache::forgetDriver('numbers-as-strings');
    app()->detectEnvironment(fn (): string => 'production');
});

it('keeps an int setting an int when it is read back from the cache', function (): void {
    setting_save('airports.default_ground_handling_cost', 250);

    // Warm the cache, then drop the request memo so the next read hits it.
    setting('airports.default_ground_handling_cost');
    app(SettingService::class)->clearMemo();

    expect(setting('airports.default_ground_handling_cost'))->toBe(250);
});

it('keeps a float addon setting a float when it is read back from the cache', function (): void {
    $addon = Addon::factory()->create(['registry_id' => 'cast/cache']);
    AddonSetting::factory()->create(['registry_id' => $addon->registry_id, 'key' => 'ratio', 'type' => 'float', 'value' => '0.25']);

    addon_setting('cast/cache', 'ratio');
    app()->forgetInstance(AddonSettingService::class);

    expect(addon_setting('cast/cache', 'ratio'))->toBe(0.25);
});
