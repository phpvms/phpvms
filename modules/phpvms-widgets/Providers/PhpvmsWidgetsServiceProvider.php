<?php

namespace Modules\PhpvmsWidgets\Providers;

use App\Contracts\Addons\AddonServiceProvider;
use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use Closure;
use Livewire\Livewire;
use Modules\PhpvmsWidgets\Http\Controllers\WeatherController;
use Override;
use Route;

/**
 * Service provider for the first-party `phpvms/phpvms-widgets` addon — the
 * home for skylight dashboard widgets.
 *
 * WHAT THIS ADDON IS
 * ------------------
 * The dashboard weather (METAR) widget used to be bundled first-party into the
 * skylight SPA (WxWidget.vue, registered in the front-end catalog). It now ships
 * from HERE as a pre-built ESM widget, proving that even core-authored widgets
 * ride the SAME addon path as third-party ones.
 *
 * The widget is built into an ESM module (see skylight/vite.config.ts) with
 * `vue` externalized, so it shares the host's single Vue instance via the SPA
 * shell's import-map. The SPA loads it at runtime by URL. The addon ALSO owns
 * the data endpoint the widget calls — this file registers both.
 *
 * HOW THE WIDGET GETS THE LIVE STATION (no usePage())
 * ---------------------------------------------------
 * An ESM addon widget must not import inertia, so it can't read usePage(). It
 * declares `props => ['icao' => '@currentAirport']`; the Dashboard resolves that
 * `@`-ref against the live page DTO props before binding (exactly like slots),
 * so the widget receives the current station as a plain `icao` prop.
 *
 * DISABLE-SAFETY
 * --------------
 * Everything this addon contributes happens inside onBoot() and
 * registerAppTiles() below: the data route and the tiles for Blade themes. A phpVMS addon's
 * ServiceProvider only boots when the addon is ENABLED. Disabled → this never
 * runs → the tiles never enter the registry AND the endpoint never exists.
 *
 * module.json ships with `"active": 0` on purpose — addons install disabled. A
 * human enables it (`php artisan addons:prime`, toggle it on in the Filament
 * Addons page, then `php artisan addons:relink` so public/ is symlinked to
 * public/ext/phpvmswidgets/ and the built widget is web-served).
 */
class PhpvmsWidgetsServiceProvider extends AddonServiceProvider
{
    /**
     * Bootstrap the addon (runs only when enabled — see class docblock).
     *
     *   1. Register the addon's own weather data route.
     *   2. Register the view, translation and Livewire namespaces the tiles use.
     */
    #[Override]
    protected function onBoot(): void
    {
        $this->registerWeatherRoutes();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'phpvmswidgets');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'phpvmswidgets');

        Livewire::addNamespace('phpvmswidgets', classNamespace: 'Modules\\PhpvmsWidgets\\Livewire');
    }

    /**
     * Register this addon's weather endpoint.
     *
     * `web` middleware gives session + cookies so the widget's credentialed
     * fetch is authenticated; `auth` restricts it to a logged-in pilot (the
     * dashboard is behind auth anyway). The addon OWNS this endpoint end-to-end.
     */
    protected function registerWeatherRoutes(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/api/phpvms-widgets/weather/{icao}', [WeatherController::class, 'show'])
                ->name('phpvms-widgets.weather');
        });
    }

    /**
     * The dashboard widgets as Livewire {@see Tiles} for Blade themes such as
     * airside.
     *
     * Each closure runs when a theme first reads the registry, per locale, so
     * `__()` is safe there.
     *
     * @return list<Closure(): Tile>
     */
    #[Override]
    public function registerAppTiles(): array
    {
        $twins = [
            ['kpi-hours', 'total-hours', 'hours', 'clock', Tile::ZONE_GRID, 10],
            ['kpi-flights', 'flights', 'flights', 'plane', Tile::ZONE_GRID, 20],
            ['kpi-balance', 'balance', 'balance', 'wallet', Tile::ZONE_GRID, 30],
            ['rank', 'rank-progress', 'rank', 'trending-up', Tile::ZONE_GRID, 40],
            ['last-flight', 'last-flight', 'last_flight', 'plane-landing', Tile::ZONE_SIDEBAR, 10],
            ['weather', 'weather', 'weather', 'cloud-sun', Tile::ZONE_SIDEBAR, 20],
        ];

        $tiles = [];

        foreach ($twins as [$id, $component, $title, $icon, $zone, $order]) {
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
