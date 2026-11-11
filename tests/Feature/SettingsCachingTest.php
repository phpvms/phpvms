<?php

declare(strict_types=1);

use App\Services\VersionService;
use App\Services\YamlDatabaseService;
use App\Settings\GeneralSettings;
use App\Settings\UnitsSettings;
use App\Support\Settings\SettingsCacheRefresher;
use Illuminate\Cache\ArrayStore;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;

/**
 * How settings reads are served: each group is loaded once per request (or
 * queued job, or Octane request), from the shared cache when it is on, and a
 * write is seen by the next request whichever path made it.
 *
 * The suite runs with the settings cache off (phpunit.xml); the tests that
 * cover the cache turn it on. `forgetScopedInstances()` is what Octane and the
 * queue worker do before every request and job.
 */

/**
 * Count the queries against the settings table while running $read.
 */
function settingsQueries(Closure $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $read();

    $count = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains(unquoteSql($query['query']), 'from settings'))
        ->count();

    DB::disableQueryLog();

    return $count;
}

function nextRequest(): void
{
    app()->forgetScopedInstances();
}

it('loads a group at most once per request, however many settings it reads', function (): void {
    nextRequest();

    $queries = settingsQueries(function (): void {
        foreach (range(1, 1000) as $i) {
            setting('units.weight');
            setting('units.distance');
            setting('units.currency');
        }
    });

    expect($queries)->toBe(1);
});

it('serves the next request from the cache when it is on', function (): void {
    enableSettingsCache();
    nextRequest();
    setting('units.weight');

    nextRequest();

    expect(settingsQueries(fn (): mixed => setting('units.weight')))->toBe(0);
});

it('shows a save to the next request, served from the cache', function (): void {
    enableSettingsCache();
    nextRequest();
    expect(setting('general.site_name'))->toBe('');

    setting_save('general.site_name', 'Lunair');
    nextRequest();

    expect(settingsQueries(fn (): mixed => setting('general.site_name')))->toBe(0)
        ->and(setting('general.site_name'))->toBe('Lunair');
});

it('shows a save made through the settings class to the next queued job', function (): void {
    enableSettingsCache();
    nextRequest();

    app(GeneralSettings::class)->fill(['site_name' => 'Lunair'])->save();

    // The queue worker forgets scoped instances before each job.
    nextRequest();

    expect(app(GeneralSettings::class)->site_name)->toBe('Lunair');
});

it('shows a value written by a data migration once migrations end', function (): void {
    enableSettingsCache();
    nextRequest();
    expect(setting('units.weight'))->toBe('lbs');

    // A migration writes the table directly, under the cached value.
    DB::table('settings')->updateOrInsert(['group' => 'units', 'name' => 'weight'], ['payload' => '"kg"', 'locked' => false]);
    event(new MigrationsEnded('up'));
    nextRequest();

    expect(setting('units.weight'))->toBe('kg');
});

it('shows settings imported from YAML to the next request', function (): void {
    enableSettingsCache();
    nextRequest();
    setting('captcha.site_key');

    app(YamlDatabaseService::class)->seedFromYaml([
        'settings' => [
            'ignore_if_exists' => false,
            'data'             => [['id' => 1000, 'group' => 'captcha', 'name' => 'site_key', 'payload' => '"imported"']],
        ],
    ]);
    nextRequest();

    expect(setting('captcha.site_key'))->toBe('imported');
});

it('scopes the cache to the installed version', function (): void {
    enableSettingsCache();
    nextRequest();
    setting('units.weight');

    /** @var ArrayStore $store */
    $store = cache()->store('array')->getStore();
    $keys = array_keys((fn (): array => $this->storage)->call($store));
    $version = app(VersionService::class)->getCurrentVersion();

    expect(collect($keys)->contains(fn (string $key): bool => str_contains($key, $version.'.settings.'.UnitsSettings::class)))->toBeTrue();
});

it('reverts the request and the cache when a save rolls back', function (): void {
    enableSettingsCache();
    nextRequest();
    setting_save('units.currency', 'USD');

    try {
        DB::transaction(function (): void {
            setting_save('units.currency', 'EUR');

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    // The outer transaction rolled back after the save had written the cache.
    app(SettingsCacheRefresher::class)->refresh(app(UnitsSettings::class));

    expect(setting('units.currency'))->toBe('USD');

    nextRequest();

    expect(setting('units.currency'))->toBe('USD');
});
