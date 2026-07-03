<?php

namespace Modules\SampleVueSlot\Providers;

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for the `phpvms/sample-vue-slot` reference addon.
 *
 * WHAT THIS ADDON PROVES
 * ----------------------
 * A third-party developer can inject a fully custom Vue component into a FIXED
 * extension point ("slot") on a first-party skylight page — WITHOUT any access
 * to the core front-end build. Here we fill the per-row slot `bids.row.actions`
 * on the My Bids table with a compact per-bid control. This is exactly how the
 * external ACARS plugin (its own repo) hooks the bids table to let a pilot fly a
 * bid via ACARS.
 *
 * SLOT vs WIDGET: a widget (see the sibling SampleVueWidget) is a dashboard card
 * the PILOT places. A slot entry is injected by the ADDON and always renders
 * wherever the page draws its matching `<PvSlot name="…">`. Same build (pre-built
 * ESM module, `vue` externalized, shared via the shell import-map); different
 * placement mechanism.
 *
 * DISABLE-SAFETY (read this before worrying about "what if it's off?")
 * -------------------------------------------------------------------
 * Everything this addon contributes to the host happens inside boot() below: the
 * single Skylight::slots()->register([...]) call. A phpVMS addon's ServiceProvider
 * only boots when the addon is ENABLED. Disabled → this never runs → the slot
 * entry never enters the registry → PvSlot renders nothing for this addon. There
 * is no runtime "enabled?" flag to check and no dead entry to clean up:
 * disable-safety is a property of *where* we register (boot()), not of any flag.
 * See App\Support\Skylight\SlotRegistry's class docblock for the same guarantee.
 *
 * module.json ships with `"active": 0` on purpose — addons install disabled. A
 * human enables it (see README: `php artisan addons:prime`, toggle it on in the
 * Filament Addons page, then `php artisan addons:relink` so public/ is symlinked
 * to public/ext/samplevueslot/ and the built slot.js is web-served).
 */
class SampleVueSlotServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the addon (runs only when enabled — see class docblock).
     */
    public function boot(): void
    {
        $this->registerSkylightSlot();
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
     * Inject the pre-built Vue component into the `bids.row.actions` slot.
     *
     * Keys map to App\Support\Skylight\SlotRegistry::register()'s contract:
     *
     *   slot      Named outlet to fill. The My Bids page draws
     *             `<PvSlot name="bids.row.actions" :context="{ bid, flight }" />`
     *             once per bid row.
     *   component UNIQUE resolver-map key. PvApp.vue builds the runtime resolver
     *             from every server slot entry that ships a `module`, so this name
     *             must not collide with any other component in the app. It is the
     *             name PvSlot looks up to render this entry.
     *   module    URL of the pre-built ESM module. Because it is set, PvApp
     *             `import()`s it at runtime via defineAsyncComponent and renders
     *             its default export. The file is emitted by the build preset to
     *             modules/SampleVueSlot/public/widgets/slot.js and served at
     *             /ext/samplevueslot/widgets/slot.js — the addon's public/ dir
     *             symlinked to public/ext/<lower-name>/ by `addons:relink`.
     *   order     Ascending render order within the slot (default 100).
     *   props     Props to pass. A string value starting with '@' is a REF the
     *             host resolves per-instance against `{ ...pageProps, ...context }`
     *             — here the row context is `{ bid, flight }`, so '@bid' and
     *             '@flight' deliver THIS row's data to the component.
     */
    protected function registerSkylightSlot(): void
    {
        Skylight::slots()->register([
            'slot'      => 'bids.row.actions',
            'component' => 'SampleBidsSlot',                       // unique resolver key
            // AddonAssetLinker symlinks public/ → public/ext/<strtolower(module NAME)>,
            // i.e. "SampleVueSlot" → "samplevueslot" (NOT the hyphenated alias).
            'module'    => '/ext/samplevueslot/widgets/slot.js',
            'order'     => 100,
            'props'     => ['bid' => '@bid', 'flight' => '@flight'], // resolved per row from PvSlot context
        ]);
    }
}
