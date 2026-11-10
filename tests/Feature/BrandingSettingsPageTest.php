<?php

declare(strict_types=1);

use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use App\Support\SettingsTabs;
use Illuminate\Support\Collection;

/**
 * `App\Filament\Pages\Settings` lists every visible core setting, grouped into
 * sections by `App\Support\SettingsTabs::group()`. These tests exercise that
 * selection directly rather than rendering the Livewire page, since it decides
 * which settings, and so which sections, appear at all.
 *
 * @return Collection<int, SettingDefinition>
 */
function settingsPageSettings(): Collection
{
    return app(SettingsRegistry::class)->all()
        ->reject(fn (SettingDefinition $definition): bool => $definition->hidden)
        ->values();
}

it('sorts general.site_name first within the general group', function (): void {
    $generalKeys = SettingsTabs::group(settingsPageSettings())->get('general')
        ->map(fn (SettingDefinition $definition): string => $definition->key)
        ->values();

    expect($generalKeys->first())->toBe('general.site_name');
});

it('excludes every branding.* key from the settings page', function (): void {
    $keys = settingsPageSettings()->map(fn (SettingDefinition $definition): string => $definition->key);

    expect($keys->filter(fn (string $key): bool => str_starts_with($key, 'branding.')))->toBeEmpty();
});

it('renders no "Branding" section', function (): void {
    expect(SettingsTabs::group(settingsPageSettings())->keys())->not->toContain('branding');
});
