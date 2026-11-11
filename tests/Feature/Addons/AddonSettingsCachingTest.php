<?php

declare(strict_types=1);

use App\Services\AddonSettingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AddonSettings\WidgetSettings;

/*
 * A page that reads an addon setting in a loop pays for the addon handle once
 * and for the settings section once per request, as core settings do. Writes
 * are seen by the same request and by the next one.
 */

beforeEach(function (): void {
    registerAddonSettings('memo/widget', [WidgetSettings::class], ['name' => 'Memo Widget']);
    addon_setting_save('memo/widget', 'count', 42);
    app()->forgetInstance(AddonSettingService::class);
    app()->forgetScopedInstances();
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

it('looks an addon and its settings up once per request', function (): void {
    $queries = addonQueries(function (): void {
        foreach (range(1, 10) as $i) {
            expect(addon_setting('memo/widget', 'count'))->toBe(42);
            addon_setting('memo/widget', 'token');
        }
    });

    expect($queries)->toHaveCount(2); // the registry_id, then the section
});

it('resolves each handle of an addon once per request', function (): void {
    $queries = addonQueries(function (): void {
        foreach (range(1, 5) as $i) {
            addon_setting('Memo Widget', 'count');
        }
    });

    // registry_id lookup, name lookup, section
    expect($queries)->toHaveCount(3);
});

it('serves the next request from the cache when it is on', function (): void {
    enableSettingsCache();
    addon_setting('memo/widget', 'count');
    app()->forgetScopedInstances();

    $queries = addonQueries(fn (): mixed => addon_setting('memo/widget', 'count'));

    // Only the handle; the section comes from the cache.
    expect(collect($queries)->filter(fn (string $query): bool => str_contains($query, 'addon_settings')))->toBeEmpty();
});

it('observes a write within the same request and the next', function (): void {
    expect(addon_setting('memo/widget', 'token'))->toBe('');

    addon_setting_save('memo/widget', 'token', 'new');

    expect(addon_setting('memo/widget', 'token'))->toBe('new');

    app()->forgetScopedInstances();

    expect(addon_setting('memo/widget', 'token'))->toBe('new');
});

it('is flushed by Octane before each request', function (): void {
    expect(config('octane.flush', []))->toContain(AddonSettingService::class);
});
