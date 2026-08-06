<?php

declare(strict_types=1);

use App\Filament\Plugins\PanelSwitcherPlugin;
use Filament\Panel;

it('exposes the panel-switcher id', function (): void {
    expect(PanelSwitcherPlugin::make()->getId())->toBe('panel-switcher');
});

it('renders an entry per panel and marks the current one active', function (): void {
    $admin = Panel::make()->id('admin')->path('admin');
    $sample = Panel::make()->id('sample')->path('admin/sample');

    $html = view('filament.plugins.panel-switcher', [
        'panels'    => [$admin, $sample],
        'current'   => $admin,
        'brandName' => 'phpvms',
        'addonsUrl' => null,
    ])->render();

    // Both panels are linked by their path...
    expect($html)->toContain(url('admin'))
        ->and($html)->toContain(url('admin/sample'))
        // ...the human labels are present...
        ->and($html)->toContain('Sample')
        // ...and the current (admin) panel is flagged active.
        ->and($html)->toContain('aria-current="true"')
        // ...and the admin panel is sorted first in the menu.
        ->and(strpos($html, url('admin')))->toBeLessThan(strpos($html, url('admin/sample')));
});

it('renders a static brand button without a dropdown when only one panel is accessible', function (): void {
    $admin = Panel::make()->id('admin')->path('admin');

    $html = trim(view('filament.plugins.panel-switcher', [
        'panels'    => [$admin],
        'current'   => $admin,
        'brandName' => 'phpvms',
        'addonsUrl' => null,
    ])->render());

    // Guarded by `@if (count($panels) > 1)` — nothing to switch to, so it
    // renders the static brand button with no dropdown markup.
    expect($html)->toContain('fi-brandbtn')
        ->and($html)->not->toContain('fi-dropdown');
});
