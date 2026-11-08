<?php

namespace Modules\PhpvmsWidgets\Providers;

use App\Contracts\Addons\AddonServiceProvider;
use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use Closure;
use Livewire\Livewire;
use Override;

/**
 * Service provider for the first-party `phpvms/widgets` addon: pilot tiles for
 * Blade themes (hours, flights, balance, rank progress, last flight, weather).
 *
 * An addon's provider only boots when the addon is enabled, so a disabled addon
 * contributes no tiles.
 */
class PhpvmsWidgetsServiceProvider extends AddonServiceProvider
{
    /**
     * Register the view, translation and Livewire namespaces the tiles use.
     */
    #[Override]
    protected function onBoot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'phpvmswidgets');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'phpvmswidgets');

        Livewire::addNamespace('phpvmswidgets', classNamespace: 'Modules\\PhpvmsWidgets\\Livewire');
    }

    /**
     * The {@see Tiles}, all offered on the dashboard and in its default layout.
     * Each closure runs when a theme first reads the registry, and again when
     * the locale changes, so `__()` is safe there.
     *
     * @return list<Closure(): Tile>
     */
    #[Override]
    public function registerAppTiles(): array
    {
        $definitions = [
            ['kpi-hours', 'total-hours', 'hours', 'clock', Tile::ZONE_GRID, 10],
            ['kpi-flights', 'flights', 'flights', 'plane', Tile::ZONE_GRID, 20],
            ['kpi-balance', 'balance', 'balance', 'wallet', Tile::ZONE_GRID, 30],
            ['rank', 'rank-progress', 'rank', 'trending-up', Tile::ZONE_GRID, 40],
            ['last-flight', 'last-flight', 'last_flight', 'plane-landing', Tile::ZONE_SIDEBAR, 10],
            ['weather', 'weather', 'weather', 'cloud-sun', Tile::ZONE_SIDEBAR, 20],
        ];

        $tiles = [];

        foreach ($definitions as [$id, $component, $title, $icon, $zone, $order]) {
            $tiles[] = fn (): Tile => Tile::livewire(
                id: $id,
                component: 'phpvmswidgets::'.$component,
                title: __('phpvmswidgets::widgets.'.$title),
                icon: $icon,
                showInDashboard: true,
                zone: $zone,
                default: true,
                order: $order,
            );
        }

        return $tiles;
    }
}
