<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonSetting;
use App\Services\AddonSettingService;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * A page that reads an addon setting in a loop paid for it every time: two
 * queries to resolve the addon's registry_id, then the value from the database
 * (or the application cache in production). Within one request, each addon
 * and each value is now looked up once, as core settings are.
 */

beforeEach(function (): void {
    $addon = Addon::factory()->create(['registry_id' => 'memo/widget', 'name' => 'Memo Widget']);
    AddonSetting::factory()->create(['registry_id' => $addon->registry_id, 'key' => 'count', 'type' => 'int', 'value' => '42']);
    AddonSetting::factory()->create(['registry_id' => $addon->registry_id, 'key' => 'label', 'type' => 'text', 'value' => 'old']);
    app()->forgetInstance(AddonSettingService::class);
});

/** @return list<string> */
function addonQueries(callable $reads): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $reads();
    $sql = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    return array_values(array_filter($sql, fn (string $q): bool => str_contains($q, 'addon')));
}

it('looks an addon and its setting up once per request', function (): void {
    $queries = addonQueries(function (): void {
        foreach (range(1, 10) as $i) {
            expect(addon_setting('memo/widget', 'count'))->toBe(42);
        }
    });

    expect($queries)->toHaveCount(2); // the registry_id, then the value
});

it('resolves each handle of an addon once per request', function (): void {
    $queries = addonQueries(function (): void {
        foreach (range(1, 5) as $i) {
            addon_setting('Memo Widget', 'count');
        }
    });

    // registry_id lookup, name lookup, value
    expect($queries)->toHaveCount(3);
});

it('reads a setting from the application cache once per request in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $reads = 0;
    Event::listen([CacheHit::class, CacheMissed::class], function ($event) use (&$reads): void {
        if (str_ends_with($event->key, 'memo/widget.count')) {
            $reads++;
        }
    });

    foreach (range(1, 5) as $i) {
        addon_setting('memo/widget', 'count');
    }

    expect($reads)->toBe(1);
});

it('observes a write within the same request', function (): void {
    expect(addon_setting('memo/widget', 'label'))->toBe('old');

    addon_setting_save('memo/widget', 'label', 'new');

    expect(addon_setting('memo/widget', 'label'))->toBe('new');
});

it('is flushed by Octane before each request', function (): void {
    expect(config('octane.flush', []))->toContain(AddonSettingService::class);
});
