<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
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

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders a fresh install with the airside layout', function (): void {
    Setting::query()->where('id', Setting::formatKey('general.theme'))->delete();
    app(SettingsSeeder::class)->run();
    forgetSettingCaches();

    User::factory()->create();

    expect(setting('general.theme'))->toBe('airside');

    $this->get('/login')->assertOk();
});

it('leaves a saved theme untouched when the seeder runs again', function (): void {
    updateSetting('general.theme', 'seven');

    app(SettingsSeeder::class)->run();
    forgetSettingCaches();

    expect(setting('general.theme'))->toBe('seven');
});
