<?php

declare(strict_types=1);
use App\Providers\Filament\BasePanelProvider;
use Illuminate\Support\ServiceProvider;

arch('providers')
    ->expect('App\Providers')
    ->toExtend(ServiceProvider::class);

/*
 * A registered provider is an entry point, not a dependency: bootstrap/providers.php
 * wires it and nothing else should reach for it. BasePanelProvider is exempt because
 * it is abstract and is never registered there -- it exists purely to be extended
 * (AdminPanelProvider, and App\Contracts\Modules\PanelProvider for module panels).
 * Split from the rule above so the exemption does not also skip toExtend().
 */
arch('providers are not used as dependencies')
    ->expect('App\Providers')
    ->not->toBeUsed()
    ->ignoring(BasePanelProvider::class);
