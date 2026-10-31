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

it('draws TallStackUI icons from the Phosphor pack the admin uses', function (): void {
    // Internal icons go through the guide; pack icons are named `phosphor.<name>.light`.
    $internal = Blade::render('<x-ts-icon icon="x-mark" internal />');
    $pack = Blade::render('<x-ts-icon name="phosphor.house.light" />');
    $sidebar = Blade::render('<x-ts-side-bar><x-ts-side-bar.item text="Home" href="/x" icon="phosphor.house.light" /></x-ts-side-bar>');

    expect($internal)->toContain('<svg')
        ->and($pack)->toContain('<svg')
        ->and($sidebar)->toContain('<svg');

    foreach (AirsideThemeServiceProvider::ICON_GUIDE as $phosphor) {
        expect(fn (): Svg => svg('phosphor-'.$phosphor.'-light'))->not->toThrow(SvgNotFound::class);
    }
});

it('maps menu and kit icon names onto Phosphor', function (): void {
    expect(NavigationIcon::name('no-such-icon-xyz'))->toBeNull()
        ->and(NavigationIcon::html('map'))->toContain('<svg');
});
