<?php

namespace Modules\SampleVueWidget\Providers;

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\ServiceProvider;
use Modules\SampleVueWidget\Http\Controllers\SamplePingController;
use Override;
use Route;

/**
 * Service provider for the `phpvms/sample-vue-widget` reference addon.
 *
 * WHAT THIS ADDON PROVES
 * ----------------------
 * A third-party developer can ship a fully custom Vue widget to the skylight SPA
 * WITHOUT any access to the core front-end build. The widget is pre-built into an
 * ESM module (see skylight/vite.config.ts) with `vue` externalized, so it shares
 * the host's single Vue instance via the SPA shell's import-map. The SPA loads it
 * at runtime by URL. The addon ALSO owns the data endpoint the widget calls —
 * this file registers both. This is a "Tier 2 — third-party Vue widget"; compare
 * the sibling SampleBladeWidget ("Tier 1 — Blade widget", no build step).
 *
 * DISABLE-SAFETY (read this before worrying about "what if it's off?")
 * -------------------------------------------------------------------
 * Everything this addon contributes to the host happens inside boot() below: the
 * data route and the Skylight::widgets()->register([...]) call. A phpVMS addon's
 * ServiceProvider only boots when the addon is ENABLED. Disabled → this never
 * runs → the widget never enters the catalog AND the /api/sample-vue/ping route
 * never exists. There is no runtime "enabled?" flag to check and no dead entry to
 * clean up: disable-safety is a property of *where* we register (boot()), not of
 * any flag. See App\Support\Skylight\WidgetRegistry's class docblock for the same
 * guarantee.
 *
 * module.json ships with `"active": 0` on purpose — addons install disabled. A
 * human enables it (see README: `php artisan addons:prime`, toggle it on in the
 * Filament Addons page, then `php artisan addons:relink` so public/ is symlinked
 * to public/ext/samplevuewidget/ and the built widget is web-served).
 */
class SampleVueWidgetServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the addon (runs only when enabled — see class docblock).
     *
     *   1. Register the addon's own data route.
     *   2. Register the pre-built Vue widget with the skylight extension hub.
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
     * Register this addon's data endpoint.
     *
     * `web` middleware gives session + cookies so the widget's credentialed
     * fetch is authenticated; `auth` restricts it to a logged-in pilot (the
     * dashboard is behind auth anyway). The route names itself in full so the
     * name stays in one place. The addon OWNS this endpoint end-to-end.
     */
    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/api/sample-vue/ping', [SamplePingController::class, 'show'])
                ->name('sample-vue-widget.ping');
        });
    }

    /**
     * Contribute the pre-built Vue widget to the skylight dashboard catalog.
     *
     * Keys map to App\Support\Skylight\WidgetRegistry::register()'s contract:
     *
     *   id          Unique, stable widget id (also the instance id).
     *   kind        'vue' → a Vue component.
     *   module      URL of the pre-built ESM module. Because it is set, the SPA
     *               `import()`s it at runtime and renders its default export
     *               (see resolve.ts). The file is emitted by the widget preset to
     *               modules/SampleVueWidget/public/widgets/sample.js and served at
     *               /ext/samplevuewidget/widgets/sample.js — the addon's public/
     *               dir symlinked to public/ext/<lower-name>/ by `addons:relink`.
     *   title/icon  Label + lucide icon in the "Add widget" menu / frame header.
     *   defaultZone 'grid' → where it lands; span 1 → one grid column.
     *   defaultOn   false → not shown until a pilot adds it from the catalog.
     *   props       Static props merged into the component — here the widget's
     *               `label`, delivered server → Inertia → resolveWidget → props.
     */
    protected function registerSkylightWidget(): void
    {
        Skylight::widgets()->register([
            'id'          => 'sample-vue-ping',
            'kind'        => 'vue',
            // AddonAssetLinker symlinks public/ → public/ext/<strtolower(module NAME)>,
            // i.e. "SampleVueWidget" → "samplevuewidget" (NOT the hyphenated alias).
            'module'      => '/ext/samplevuewidget/widgets/sample.js',
            'title'       => 'Sample Vue widget',
            'icon'        => 'boxes',                 // lucide name
            'defaultZone' => 'grid',
            'span'        => 1,
            'defaultOn'   => false,
            'props'       => ['label' => 'Hello from the addon'],
        ]);
    }
}
