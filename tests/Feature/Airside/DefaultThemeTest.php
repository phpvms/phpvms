<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * 9.2: airside is the default theme for fresh installs. An install that
 * already saved a theme keeps it.
 *
 * tests/TestCase.php pins every test to skylight, so these tests remove that
 * row first.
 */
it('defaults to airside on a fresh install', function (): void {
    DB::table('settings')->where('group', 'general')->where('name', 'theme')->delete();
    reloadSettings();

    expect(setting('general.theme'))->toBe('airside');
});

it('keeps a saved theme after the settings are reloaded', function (): void {
    updateSetting('general.theme', 'seven');
    reloadSettings();

    expect(setting('general.theme'))->toBe('seven');
});
