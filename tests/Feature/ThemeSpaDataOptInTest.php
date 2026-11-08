<?php

declare(strict_types=1);

use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;

/**
 * theme-render-contract: "Blade themes can receive the SPA page data".
 */
function useProbeTheme(array $settings): void
{
    Config::set('themes.themes.probe', [
        'name'       => 'probe',
        'extends'    => null,
        'views-path' => 'seven',
        ...$settings,
    ]);
    Theme::scanThemes();
    Theme::set('probe');
}

it('gives an opted-in Blade theme the SPA props, without building the Blade data', function (): void {
    useProbeTheme(['data' => 'spa']);

    $response = response()->themed(
        'Dashboard',
        'dashboard.index',
        bladeData: fn (): array => throw new RuntimeException('Blade data must not be built'),
        spa: fn (): array => ['dashboard' => 'spa-payload'],
    );

    expect($response)->toBeInstanceOf(View::class)
        ->and($response->getData())->toBe(['dashboard' => 'spa-payload']);
});

it('keeps the legacy Blade data for a theme that does not opt in', function (): void {
    Theme::set('seven');

    $response = response()->themed(
        'Dashboard',
        'dashboard.index',
        bladeData: fn (): array => ['legacy' => true],
        spa: fn (): array => throw new RuntimeException('SPA props must not be built'),
    );

    expect($response->getData())->toBe(['legacy' => true]);
});

it('uses the Inertia projection of a presenter for an opted-in Blade theme', function (): void {
    useProbeTheme(['data' => 'spa']);

    $presenter = new class()
    {
        public function toInertiaArray(): array
        {
            return ['kind' => 'inertia'];
        }

        public function toBladeArray(): array
        {
            return ['kind' => 'blade'];
        }
    };

    expect(response()->themed('Dashboard', 'dashboard.index', $presenter)->getData())->toBe(['kind' => 'inertia']);
});
