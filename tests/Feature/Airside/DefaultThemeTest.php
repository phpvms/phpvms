<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\SettingService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * 9.2: airside is the default theme for fresh installs. An install that
 * already saved a theme keeps it.
 *
 * tests/TestCase.php pins every test to skylight after seeding, so these
 * tests put the row back to what the seeder ships first.
 */
function forgetSettingCaches(): void
{
    Cache::flush();
    app(SettingService::class)->clearMemo();
}

it('seeds airside as the default theme on a fresh install', function (): void {
    Setting::query()->where('id', Setting::formatKey('general.theme'))->delete();
    app(SettingsSeeder::class)->run();
    forgetSettingCaches();

    expect(setting('general.theme'))->toBe('airside');
});

it('leaves a saved theme untouched when the seeder runs again', function (): void {
    updateSetting('general.theme', 'seven');

    app(SettingsSeeder::class)->run();
    forgetSettingCaches();

    expect(setting('general.theme'))->toBe('seven');
});
