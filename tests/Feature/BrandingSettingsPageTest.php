<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * `App\Filament\Pages\Settings::getFormSchema()` builds its tabs from exactly
 * this query (`app/Filament/Pages/Settings.php:175-177`): every non-hidden
 * row, ordered by `order`, grouped by `group`. These tests exercise that
 * query directly rather than rendering the Livewire page, since it is the
 * one mechanism that decides both field order and tab membership.
 */
function settingsPageQuery(): Collection
{
    return Setting::where('type', '!=', 'hidden')->orderBy('order')->get();
}

beforeEach(function (): void {
    $migration = require base_path('database/migrations_data/2026_08_13_000000_branding_settings.php');
    $migration->up();
});

it('sorts general.site_name first within the general group', function (): void {
    $generalKeys = settingsPageQuery()
        ->where('group', 'general')
        ->pluck('key')
        ->values();

    expect($generalKeys->first())->toBe('general.site_name');
});

it('excludes every branding.* key from the settings page query', function (): void {
    $keys = settingsPageQuery()->pluck('key');

    expect($keys)->not->toContain('branding.brand_color')
        ->and($keys)->not->toContain('branding.logo_url')
        ->and($keys)->not->toContain('branding.logo_32_url')
        ->and($keys)->not->toContain('branding.logo_64_url')
        ->and($keys)->not->toContain('branding.logo_180_url')
        ->and($keys)->not->toContain('branding.banner_url');
});

it('renders no "Branding" tab', function (): void {
    $groups = settingsPageQuery()->pluck('group')->unique()->values();

    expect($groups)->not->toContain('branding');
});
