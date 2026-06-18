<?php

declare(strict_types=1);

use Filament\Panel;
use Modules\Sample\Filament\Resources\SampleResource;
use Modules\Sample\Providers\Filament\SampleAdminPanelProvider;

it('configures id, path, brand and the panel switcher from the base contract', function (): void {
    $provider = new SampleAdminPanelProvider(app());

    $panel = $provider->panel(Panel::make());

    expect($panel->getId())->toBe('sample')
        ->and($panel->getPath())->toBe('admin/sample')
        ->and($panel->hasPlugin('panel-switcher'))->toBeTrue();
});

it("discovers the module's own Filament resources", function (): void {
    $provider = new SampleAdminPanelProvider(app());

    $panel = $provider->panel(Panel::make());
    $panel->register();

    expect($panel->getResources())->toContain(SampleResource::class);
});
