<?php

namespace Modules\PhpvmsWidgets\Providers;

use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use App\Support\Extensions\Facades\Extensions;
use Illuminate\Support\ServiceProvider;
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
 * ride the SAME addon path as third-party ones (compare modules/phpvms-sample-vue-widget).
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
 * Everything this addon contributes happens inside boot() below: the data route,
 * the Extensions::widgets()->register([...]) calls for skylight, and the
 * Tiles registrations for Blade themes. A phpVMS addon's
 * ServiceProvider only boots when the addon is ENABLED. Disabled → this never
 * runs → the widget never enters the catalog AND the endpoint never exists.
 *
 * module.json ships with `"active": 0` on purpose — addons install disabled. A
 * human enables it (`php artisan addons:prime`, toggle it on in the Filament
 * Addons page, then `php artisan addons:relink` so public/ is symlinked to
 * public/ext/phpvmswidgets/ and the built widget is web-served).
 */
class PhpvmsWidgetsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the addon (runs only when enabled — see class docblock).
     *
     *   1. Register the addon's own weather data route.
     *   2. Register the bundled dashboard widgets with the extensions hub.
     *   3. Register their Livewire twins as tiles, for Blade themes.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerSkylightWidget();
        $this->registerTiles();
    }

    /**
     * Nothing to bind — all registration is deferred to boot() so it only
     * happens when the addon is enabled. Kept empty on purpose.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Register this addon's weather endpoint.
     *
     * `web` middleware gives session + cookies so the widget's credentialed
     * fetch is authenticated; `auth` restricts it to a logged-in pilot (the
     * dashboard is behind auth anyway). The addon OWNS this endpoint end-to-end.
     */
    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/api/phpvms-widgets/weather/{icao}', [WeatherController::class, 'show'])
                ->name('phpvms-widgets.weather');
        });
    }

    /**
     * Contribute the bundled dashboard widgets to the skylight catalog, all as
     * pre-built ESM modules served from /ext/phpvmswidgets/widgets/*.js.
     *
     * These used to live in the core SPA (lib/widgets/catalog.ts + a resolver
     * map + a WsKpi presentation component). They now ride the same addon path
     * as third-party widgets — proving core-authored widgets are not special.
     *
     * Each `id`, `title`, `icon`, `defaultZone`, `span` and `defaultOn` matches
     * the previous first-party catalog entry, so existing saved layouts keep
     * resolving unchanged. The KPI/rank/last-flight widgets take NO endpoint:
     * their data arrives as `@`-ref props (DashboardData keys) resolved by the
     * Dashboard before binding — the same mechanism that feeds weather its
     * station, so none of them import inertia/usePage(). Registration order sets
     * the default-layout order within each zone (route stays a core widget and
     * renders first in the grid).
     *
     * AddonAssetLinker symlinks public/ → public/ext/<strtolower(module NAME)>,
     * i.e. "PhpvmsWidgets" → "phpvmswidgets" (NOT the hyphenated alias); the
     * bundles are emitted by the widget preset to modules/phpvms-widgets/public/widgets/.
     */
    protected function registerSkylightWidget(): void
    {
        $widgets = Extensions::widgets();

        $widgets->register([
            'id'          => 'kpi-hours',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmswidgets/widgets/hours.js',
            'title'       => 'Total hours',
            'icon'        => 'clock',
            'defaultZone' => 'grid',
            'span'        => 1,
            'defaultOn'   => true,
            'props'       => ['value' => '@flightTimeMinutes'],
        ]);

        $widgets->register([
            'id'          => 'kpi-flights',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmswidgets/widgets/flights.js',
            'title'       => 'Flights',
            'icon'        => 'plane',
            'defaultZone' => 'grid',
            'span'        => 1,
            'defaultOn'   => true,
            'props'       => ['value' => '@flights'],
        ]);

        $widgets->register([
            'id'          => 'kpi-balance',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmswidgets/widgets/balance.js',
            'title'       => 'Balance',
            'icon'        => 'wallet',
            'defaultZone' => 'grid',
            'span'        => 1,
            'defaultOn'   => true,
            'props'       => ['balance' => '@balance'],
        ]);

        $widgets->register([
            'id'          => 'rank',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmswidgets/widgets/rank.js',
            'title'       => 'Rank progress',
            'icon'        => 'trending-up',
            'defaultZone' => 'grid',
            'span'        => 1,
            'defaultOn'   => true,
            'props'       => ['rank' => '@rank'],
        ]);

        $widgets->register([
            'id'          => 'last-flight',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmswidgets/widgets/lastflight.js',
            'title'       => 'Last flight',
            'icon'        => 'plane-landing',
            'defaultZone' => 'sidebar',
            'defaultOn'   => true,
            'props'       => ['pirep' => '@lastPirep'],
        ]);

        $widgets->register([
            'id'          => 'weather',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmswidgets/widgets/weather.js',
            'title'       => 'Weather (METAR)',
            'icon'        => 'cloud-sun',
            'defaultZone' => 'sidebar',
            'defaultOn'   => true,
            'props'       => ['icao' => '@currentAirport'],
        ]);
    }

    /**
     * Register the Livewire twins of the skylight widgets as {@see Tiles}, for
     * Blade themes such as airside.
     *
     * Same ids, zones and default-on flags as the skylight entries above, so a
     * pilot sees the same dashboard whichever theme is active. Each closure runs
     * when a theme first reads the registry, per locale, so `__()` is safe there.
     */
    protected function registerTiles(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'phpvmswidgets');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'phpvmswidgets');

        Livewire::addNamespace('phpvmswidgets', classNamespace: 'Modules\\PhpvmsWidgets\\Livewire');

        $tiles = $this->app->make(Tiles::class);

        $twins = [
            ['kpi-hours', 'total-hours', 'hours', 'clock', Tile::ZONE_GRID, 10],
            ['kpi-flights', 'flights', 'flights', 'plane', Tile::ZONE_GRID, 20],
            ['kpi-balance', 'balance', 'balance', 'wallet', Tile::ZONE_GRID, 30],
            ['rank', 'rank-progress', 'rank', 'trending-up', Tile::ZONE_GRID, 40],
            ['last-flight', 'last-flight', 'last_flight', 'plane-landing', Tile::ZONE_SIDEBAR, 10],
            ['weather', 'weather', 'weather', 'cloud-sun', Tile::ZONE_SIDEBAR, 20],
        ];

        foreach ($twins as [$id, $component, $title, $icon, $zone, $order]) {
            $tiles->register($id, fn (): Tile => Tile::livewire(
                id: $id,
                component: 'phpvmswidgets::'.$component,
                title: __('phpvmswidgets::widgets.'.$title),
                icon: $icon,
                showInDashboard: true,
                zone: $zone,
                default: true,
                order: $order,
            ));
        }
    }
}
