<?php

declare(strict_types=1);

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Svg;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Blade;
use Modules\PhpvmsAirsideTheme\Providers\AirsideThemeServiceProvider;
use Modules\PhpvmsAirsideTheme\Support\NavigationIcon;

/**
 * airside's provider: the bundled theme registers, owns the TallStackUI
 * configuration, and core carries none of it.
 */
beforeEach(function (): void {
    Theme::set('airside');
});

it('registers the bundled theme with its settings', function (): void {
    expect(Theme::exists('airside'))->toBeTrue()
        ->and(theme_kind())->toBe('blade')
        ->and(theme_uses_spa_data())->toBeTrue();
});

it('renders a TallStackUI button with the ts- prefix', function (): void {
    $html = Blade::render('<x-ts-button>Probe</x-ts-button>');

    expect($html)->toContain('role="button"')
        ->and($html)->toContain('Probe');
});

it('registers the airside anonymous component namespace', function (): void {
    expect(Blade::render('<x-airside::probe />'))->toContain('airside-probe');
});

it('keeps TallStackUI code out of core config and providers', function (): void {
    expect(base_path('config/tallstackui.php'))->not->toBeFile();

    foreach (glob(app_path('Providers').'/{,*/}*.php', GLOB_BRACE) as $file) {
        expect(file_get_contents($file))->not->toContain('TallStackUi', 'tallstackui', 'ts-ui');
    }

    foreach (glob(config_path('*.php')) as $file) {
        expect(file_get_contents($file))->not->toContain('TallStackUi', 'tallstackui');
    }
});

it('draws TallStackUI icons from the Phosphor pack the admin uses', function (): void {
    // Internal icons go through the guide; pack icons are named `phosphor.<name>.light`.
    $internal = Blade::render('<x-ts-icon icon="x-mark" internal />');
    $pack = Blade::render('<x-ts-icon name="phosphor.house.light" />');
    $sidebar = Blade::render('<x-ts-side-bar><x-ts-side-bar.item text="Home" href="/x" icon="phosphor.house.light" /></x-ts-side-bar>');

    expect(config('ts-ui.components.icon.1.type'))->toBe('codeat3/blade-phosphor-icons')
        ->and(config('ts-ui.components.icon.1.custom.guide.x-mark'))->toBe('phosphor-x-light')
        ->and($internal)->toContain('<svg')
        ->and($pack)->toContain('<svg')
        ->and($sidebar)->toContain('<svg');

    foreach (AirsideThemeServiceProvider::ICON_GUIDE as $phosphor) {
        expect(fn (): Svg => svg('phosphor-'.$phosphor.'-light'))->not->toThrow(SvgNotFound::class);
    }
});

it('maps menu and kit icon names onto Phosphor', function (): void {
    expect(NavigationIcon::tallstack('layout-dashboard'))->toBe('phosphor.squares-four.light')
        ->and(NavigationIcon::tallstack('i-tabler-plane'))->toBe('phosphor.airplane-tilt.light')
        ->and(NavigationIcon::name('x-mark'))->toBe('x')
        ->and(NavigationIcon::name('no-such-icon-xyz'))->toBeNull()
        ->and(NavigationIcon::html('map'))->toContain('<svg');
});
