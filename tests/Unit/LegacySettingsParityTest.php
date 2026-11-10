<?php

declare(strict_types=1);

use App\Enums\AltitudeUnit;
use App\Enums\DistanceUnit;
use App\Enums\SpeedUnit;
use App\Enums\TemperatureUnit;
use App\Enums\VolumeUnit;
use App\Enums\WeightUnit;
use App\Support\RenamedSettings;
use App\Support\Settings\SettingsRegistry;

/**
 * The settings classes declare every setting the settings table offered, with
 * the same default, and the unit enums offer exactly the old select options.
 * Compared against tests/data/legacy_settings.php, the seeder's last state.
 */
function legacySetting(string $key): array
{
    return collect(require base_path('tests/data/legacy_settings.php'))->firstWhere('key', $key);
}

it('declares every legacy setting with the same default', function (): void {
    $registry = app(SettingsRegistry::class);

    foreach (require base_path('tests/data/legacy_settings.php') as $legacy) {
        $definition = $registry->find(RenamedSettings::resolve($legacy['key']));

        expect($definition)->not->toBeNull("{$legacy['key']} is not declared by any settings class");

        $default = $definition->castLegacy($legacy['value']);

        expect($definition->present($definition->default))
            ->toEqual($definition->present($default), "{$legacy['key']} changed its default")
            ->and($definition->hidden)->toBe($legacy['type'] === 'hidden', "{$legacy['key']} changed visibility");
    }
});

it('offers the old select options as unit enum cases', function (string $key, string $enum): void {
    $options = collect(explode(',', legacySetting($key)['options']))
        ->map(fn (string $option): string => explode('=', $option)[0])
        ->all();

    expect(array_column($enum::cases(), 'value'))->toBe($options);
})->with([
    ['units.distance', DistanceUnit::class],
    ['units.weight', WeightUnit::class],
    ['units.fuel', WeightUnit::class],
    ['units.speed', SpeedUnit::class],
    ['units.altitude', AltitudeUnit::class],
    ['units.volume', VolumeUnit::class],
    ['units.temperature', TemperatureUnit::class],
]);
