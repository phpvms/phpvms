<?php

declare(strict_types=1);

namespace App\Features\Tiles;

use Illuminate\Support\ServiceProvider;

/**
 * Binds the tile registry and registers the first-party element tiles.
 */
class TilesServiceProvider extends ServiceProvider
{
    private const string ELEMENTS_MODULE = '/build/skylight/elements.js';

    /**
     * Singleton is safe under Octane: registrations happen at boot, and the
     * built tiles are immutable.
     */
    public function register(): void
    {
        $this->app->singleton(Tiles::class);
    }

    /**
     * The light-DOM custom elements from fe-vue (src/elements/*.ce.vue). They are
     * building blocks for pages, not dashboard tiles.
     */
    public function boot(): void
    {
        $tiles = $this->app->make(Tiles::class);

        $tiles->register(fn (): Tile => Tile::element('pv-hero', 'pv-hero', self::ELEMENTS_MODULE, 'Hero', 'image', showInDashboard: false));
        $tiles->register(fn (): Tile => Tile::element('pv-callout', 'pv-callout', self::ELEMENTS_MODULE, 'Callout', 'info', showInDashboard: false));
        $tiles->register(fn (): Tile => Tile::element('pv-stat-group', 'pv-stat-group', self::ELEMENTS_MODULE, 'Stat group', 'bar-chart-3', showInDashboard: false));
    }
}
