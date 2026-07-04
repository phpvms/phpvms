<?php

namespace Modules\SampleBladeWidget\Providers;

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\ServiceProvider;
use Override;
use Route;

/**
 * Service provider for the `phpvms/sample-blade-widget` reference addon.
 *
 * WHAT THIS ADDON PROVES
 * ----------------------
 * An addon developer can contribute a native-looking dashboard widget to the
 * skylight SPA with nothing but a controller + a Blade fragment. No Vue, no
 * front-end build step, no compiled assets. The widget's server logic never
 * ships to the browser — only the rendered HTML does.
 *
 * This is a "Tier 1 — Blade widget". The heavy lifting (hosting the HTML,
 * fetching it credentialed, intercepting form submits) is done by the SPA's
 * generic host shell, resources/js/apps/fe/apps/spa/components/widgets/BladeWidget.vue.
 * You just register a widget definition and return a layout-less fragment.
 *
 * DISABLE-SAFETY (read this before worrying about "what if it's off?")
 * -------------------------------------------------------------------
 * The ONLY place this addon touches the SPA is inside boot() below, via
 * Skylight::widgets()->register([...]). A phpVMS addon's ServiceProvider only
 * boots when the addon is ENABLED. A disabled addon's provider never boots, so
 * register() is never called, so the widget simply never appears in the SPA's
 * catalog. There is no runtime "is this enabled?" check to add, no catalog
 * entry to clean up, and no dead endpoint to guard: disable-safety is a
 * property of *where* we register (boot()), not of any flag we set. See the
 * class docblock of App\Support\Skylight\WidgetRegistry for the same guarantee.
 *
 * Note module.json ships with `"active": 0` on purpose — addon installs are
 * disabled by default. A human enables it (see this module's README:
 * `php artisan addons:prime`, then toggle it on in the Filament Addons page).
 */
class SampleBladeWidgetServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the addon.
     *
     * Runs only when the addon is enabled (see class docblock). Three jobs:
     *   1. Tell Laravel where this module's Blade views live, under the
     *      `sample-blade-widget::` namespace.
     *   2. Register the module's web route(s).
     *   3. Register the dashboard widget with the skylight extension hub.
     */
    public function boot(): void
    {
        $this->registerViews();
        $this->registerRoutes();
        $this->registerSkylightWidget();
    }

    /**
     * Register the service bindings.
     *
     * Nothing to bind for a Blade widget — all registration is deferred to
     * boot() so it only happens when the addon is enabled. Kept empty on
     * purpose (and documented, so a copy-me reader knows it is intentional).
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Point the view factory at this module's Blade fragments.
     *
     * After this, `view('sample-blade-widget::notams')` resolves to
     * Resources/views/notams.blade.php. The namespace prefix keeps our view
     * names from colliding with the core app or other addons.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'sample-blade-widget');
    }

    /**
     * Register this module's web routes.
     *
     * The group applies:
     *   - `web`  middleware: session + cookies + CSRF, so the SPA's credentialed
     *            fetch is authenticated and the CSRF token the shell sends on
     *            island form submits is validated.
     *   - `auth` middleware: the widget endpoint is only reachable by a
     *            logged-in pilot (the dashboard is behind auth anyway).
     *
     * We deliberately DO NOT set an `as` (name) prefix on the group: the route
     * inside web.php names itself `sample-blade-widget.notams` in full. The
     * widget `endpoint` below uses the LITERAL path '/widgets/sample-notams'
     * (NOT route() by name — see registerSkylightWidget()'s note on boot-time
     * route ordering); the route name is used only by the Blade fragment's
     * <form action>, which renders at request time. Keeping the full name in one
     * place avoids the classic "prefix.prefix.name" double-up bug.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'middleware' => ['web', 'auth'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/web.php');
        });
    }

    /**
     * Contribute the widget to the skylight dashboard catalog.
     *
     * This single call is the entire integration surface. The keys map to
     * App\Support\Skylight\WidgetRegistry::register()'s documented contract:
     *
     *   id          Unique, stable widget id (also the instance id).
     *   kind        'blade' → server-rendered fragment, hosted by BladeWidget.vue.
     *   mode        'island' → the shell fetches our endpoint, injects the HTML,
     *               and progressively enhances any <form> inside it (intercepts
     *               submit, adds the X-CSRF-TOKEN header, re-fetches, swaps the
     *               HTML in place — no full page reload). The alternative,
     *               'iframe', would host us in a same-origin <iframe> instead.
     *   title       Label shown in the "Add widget" menu and the frame header.
     *   icon        A lucide icon name (rendered by the SPA).
     *   endpoint    URL the shell fetches. Use a LITERAL path here, NOT
     *               route('sample-blade-widget.notams'). Addon ServiceProviders
     *               boot early — route registration order across providers is not
     *               guaranteed, so route() by name can throw RouteNotFoundException
     *               at boot. The endpoint is just a serialized string in the shared
     *               props, so a literal path is both simpler and safe. (The Blade
     *               fragment's <form action> may still use route() — that renders
     *               at REQUEST time, when all routes exist.) Keep this path in sync
     *               with Http/Routes/web.php.
     *   defaultZone 'sidebar' → where it lands in the default layout.
     *   defaultOn   false → not shown until a pilot adds it from the catalog.
     */
    protected function registerSkylightWidget(): void
    {
        Skylight::widgets()->register([
            'id'          => 'sample-blade-notams',
            'kind'        => 'blade',
            'mode'        => 'island',
            'title'       => 'Station NOTAMs (sample)',
            'icon'        => 'triangle-alert',           // lucide name
            'endpoint'    => '/widgets/sample-notams',   // literal path (see note above)
            'defaultZone' => 'sidebar',
            'defaultOn'   => false,
        ]);
    }
}
