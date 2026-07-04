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
     *   2. Register the pre-built Vue weather widget with the skylight hub.
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
     * Contribute the pre-built Vue weather widget to the skylight dashboard
     * catalog.
     *
     *   id          Stable widget id — 'weather', matching the previous
     *               first-party entry so existing layouts keep resolving.
     *   kind        'vue' → a Vue component.
     *   module      URL of the pre-built ESM module. AddonAssetLinker symlinks
     *               public/ → public/ext/<strtolower(module NAME)>, i.e.
     *               "PhpvmsDashboard" → "phpvmsdashboard" (NOT the hyphenated
     *               alias). Emitted by the widget preset to
     *               modules/phpvms-dashboard/public/widgets/weather.js.
     *   props       `icao => '@currentAirport'` — a page-DTO ref resolved by the
     *               Dashboard before binding, delivering the live station to the
     *               widget without it importing inertia.
     */
    protected function registerSkylightWidget(): void
    {
        Skylight::widgets()->register([
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
