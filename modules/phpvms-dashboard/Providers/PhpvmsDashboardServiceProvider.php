<?php

namespace Modules\PhpvmsDashboard\Providers;

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\ServiceProvider;
use Modules\PhpvmsDashboard\Http\Controllers\WeatherController;
use Override;
use Route;

/**
 * Service provider for the first-party `phpvms/phpvms-dashboard` addon — the
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
 * Everything this addon contributes happens inside boot() below: the data route
 * and the Skylight::widgets()->register([...]) call. A phpVMS addon's
 * ServiceProvider only boots when the addon is ENABLED. Disabled → this never
 * runs → the widget never enters the catalog AND the endpoint never exists.
 *
 * module.json ships with `"active": 0` on purpose — addons install disabled. A
 * human enables it (`php artisan addons:prime`, toggle it on in the Filament
 * Addons page, then `php artisan addons:relink` so public/ is symlinked to
 * public/ext/phpvmsdashboard/ and the built widget is web-served).
 */
class PhpvmsDashboardServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the addon (runs only when enabled — see class docblock).
     *
     *   1. Register the addon's own weather data route.
     *   2. Register the bundled dashboard widgets with the skylight hub.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerSkylightWidget();
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
            Route::get('/api/phpvms-dashboard/weather/{icao}', [WeatherController::class, 'show'])
                ->name('phpvms-dashboard.weather');
        });
    }

    /**
     * Contribute the bundled dashboard widgets to the skylight catalog, all as
     * pre-built ESM modules served from /ext/phpvmsdashboard/widgets/*.js.
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
     * i.e. "PhpvmsDashboard" → "phpvmsdashboard" (NOT the hyphenated alias); the
     * bundles are emitted by the widget preset to modules/phpvms-dashboard/public/widgets/.
     */
    protected function registerSkylightWidget(): void
    {
        $widgets = Skylight::widgets();

        $widgets->register([
            'id'          => 'kpi-hours',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmsdashboard/widgets/hours.js',
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
            'module'      => '/ext/phpvmsdashboard/widgets/flights.js',
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
            'module'      => '/ext/phpvmsdashboard/widgets/balance.js',
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
            'module'      => '/ext/phpvmsdashboard/widgets/rank.js',
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
            'module'      => '/ext/phpvmsdashboard/widgets/lastflight.js',
            'title'       => 'Last flight',
            'icon'        => 'plane-landing',
            'defaultZone' => 'sidebar',
            'defaultOn'   => true,
            'props'       => ['pirep' => '@lastPirep'],
        ]);

        $widgets->register([
            'id'          => 'weather',
            'kind'        => 'vue',
            'module'      => '/ext/phpvmsdashboard/widgets/weather.js',
            'title'       => 'Weather (METAR)',
            'icon'        => 'cloud-sun',
            'defaultZone' => 'sidebar',
            'defaultOn'   => true,
            'props'       => ['icao' => '@currentAirport'],
        ]);
    }
}
