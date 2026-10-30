<?php

declare(strict_types=1);

use App\Services\SettingService;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Tests for the in-request SettingService memo (Tier 1 cache).
 *
 * The SettingsSeeder runs in beforeEach (Pest.php), so all standard
 * settings (e.g. 'general.theme') are available.
 *
 * Tests run in a non-production environment, so setting() calls
 * retrieve() directly (no Cache::remember wrapper) — which means
 * the memo is exercised on every call.
 */
it('repeated reads of the same key hit the source at most once', function (): void {
    DB::enableQueryLog();

    $a = setting('general.theme');
    $b = setting('general.theme');
    $c = setting('general.theme');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // All three reads must return the same value.
    expect($a)->toBe($b)->toBe($c);

    // Only one DB query should have targeted the settings table for this key.
    $settingQueries = collect($queries)
        ->filter(fn (array $q): bool => str_contains((string) $q['query'], 'settings')
            && in_array('general_theme', $q['bindings'], strict: true))
        ->count();

    expect($settingQueries)->toBe(1);
});

it('a write within the same request is observed on subsequent reads', function (): void {
    $original = setting('general.theme');

    setting_save('general.theme', 'test_dark');

    $updated = setting('general.theme');

    expect($updated)->toBe('test_dark');
    expect($updated)->not->toBe($original);
});

it('SettingService is registered in the Octane flush list', function (): void {
    // Under Octane the singleton (and its memo) is discarded before each
    // request, so a setting change is observed on the next request.
    expect(config('octane.flush', []))->toContain(SettingService::class);
});

it('reads a setting from the application cache once per request in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $reads = 0;
    Event::listen([CacheHit::class, CacheMissed::class], function ($event) use (&$reads): void {
        if (str_ends_with($event->key, 'units.weight')) {
            $reads++;
        }
    });

    $values = array_map(fn (): mixed => setting('units.weight'), range(1, 5));

    expect(array_unique($values))->toHaveCount(1)
        ->and($reads)->toBe(1);
});

it('observes a write within the same request in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    setting('general.theme');
    setting_save('general.theme', 'test_dark');

    expect(setting('general.theme'))->toBe('test_dark');
});
