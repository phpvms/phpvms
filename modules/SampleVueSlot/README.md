# Sample Vue Slot (`phpvms/sample-vue-slot`)

A **third-party-style, copy-me reference addon** that injects a **pre-built Vue
component (ESM module)** into a **page slot** on the **skylight SPA** — the
per-row `bids.row.actions` slot of the **My Bids** table.

This is the template to copy when your addon needs to add a control *inside a
first-party page* rather than as a standalone dashboard card. **The real external
ACARS plugin uses this exact pattern** to add a per-bid "fly via ACARS" control
to the bids table.

> See also `site/drafts/extending-the-spa-theme.mdx` for the narrative guide to
> extending the skylight SPA (widgets, slots, and the build).

---

## Slot vs Widget — which reference do I copy?

| | **Widget** (`SampleVueWidget`) | **Slot** (this addon) |
|---|---|---|
| Placed by | the **pilot** (adds it to their board) | the **addon** (always renders) |
| Renders where | a dashboard grid/zone frame | wherever a page draws `<PvSlot name="…">` |
| Instances | one per board placement | one **per host-provided context** (here: per bid row) |
| Gets its data from | its own endpoint / static `props` | the page's per-instance `context` via `@`-refs |
| Registry | `Skylight::widgets()->register(...)` | `Skylight::slots()->register(...)` |

Both ship the **same kind of build**: a pre-built ESM module with `vue`
externalized, loaded by the SPA at runtime by URL. Only the *placement mechanism*
differs.

---

## What this addon injects

The My Bids page (`resources/skylight/apps/spa/pages/Flights/Bids.vue`) exposes a
per-row extension point:

```vue
<!-- rendered once per bid row -->
<PvSlot name="bids.row.actions" :context="{ bid: row.bid, flight: row.flight }" />
```

This addon registers a Vue component for that slot. The SPA then renders our
component **once per bid row**, inside each row's actions cell. Our component
(`ui/SampleBidsSlot.vue`) draws a compact `◯ ACARS <ident>` badge/button
that toggles to `◉ ACARS <ident>` on click — a stand-in for the ACARS plugin's
real "connect / fly this bid" control.

---

## The `@bid` / `@flight` context mechanism

The ServiceProvider registers the slot entry with a `props` map whose values are
**`@`-refs**:

```php
Skylight::slots()->register([
  'slot'      => 'bids.row.actions',
  'component' => 'SampleBidsSlot',                       // unique resolver key
  'module'    => '/ext/samplevueslot/widgets/slot.js', // pre-built ESM URL
  'order'     => 100,
  'props'     => ['bid' => '@bid', 'flight' => '@flight'],
]);
```

A value starting with `@` is a **reference**, not a literal. At render time,
`PvSlot` resolves each `@`-ref against `{ ...pageProps, ...context }` where
`context` is the per-row object the page passed (`{ bid, flight }`). So:

- `'bid' => '@bid'` → the component's `bid` prop = **this row's** `bid`
- `'flight' => '@flight'` → the component's `flight` prop = **this row's** `flight`

The **host resolves the refs**; the component just declares
`defineProps<{ bid?, flight? }>()` and reads them. Context keys override page-level
props, so a `@bid` ref works even though `bid` is not a page-level prop.

Relevant source (read-only):
- `app/Support/Skylight/SlotRegistry.php` — the `register()` contract.
- `resources/skylight/apps/spa/components/pv/PvSlot.vue` — resolves `@`-refs against `{ ...pageProps, ...context }`.
- `resources/skylight/apps/spa/components/pv/PvApp.vue` — for each server slot entry with a `module`, registers `resolver[component] = defineAsyncComponent(() => import(module))`.

Because of PvApp's resolver step, a slot entry needs **both** a unique
`component` name (the resolver key) **and** a `module` URL (what to import).

---

## The ESM / import-map / shared-Vue build

The component is built with the shared addon preset
(`resources/skylight/addon-build/widget-preset.ts`) via
`ui/vite.config.ts`. The build produces a **pre-built ESM module** in Vue
library mode with **`vue` externalized**:

- The output contains a bare `import { ... } from "vue"` — it does **not** bundle
  Vue. At runtime that bare specifier is resolved by the SPA shell's **import-map**
  to the host's **single shared Vue instance**, so host + every addon share one
  ESM Vue (no duplicated reactivity).
- The component imports **nothing skylight-internal** — only `vue`. All design
  patterns (`--pv-*` tokens with fallbacks) are used inline, so the file can be
  lifted into the external ACARS plugin repo unchanged.
- Output is **un-hashed** (`slot.js`) so the `module` URL stays constant.

Output path: `modules/SampleVueSlot/public/widgets/slot.js`, served at
`/ext/samplevueslot/widgets/slot.js` once `public/` is relinked.

> CSS: the shared preset uses `vite-plugin-css-injected-by-js`, so the
> component's `<style scoped>` block is **folded into the JS bundle** and
> auto-injected at runtime — there is no separate CSS file to load or link. The
> component still sets its **visual essentials inline** with `--pv-*` tokens
> (and fallbacks) because those tokens are defined by the host theme, not by the
> addon; inline values keep the control legible even if a token is missing.

---

## Build, relink, enable

**Build the slot module** (from `resources/skylight/`):

```bash
# Just this addon:
pnpm exec vite build -c ../../modules/SampleVueSlot/ui/vite.config.ts

# Or all in-repo addons at once:
pnpm build:addons
```

This writes `modules/SampleVueSlot/public/widgets/slot.js`.

**Enable + serve it:**

```bash
php artisan addons:prime     # discover the addon
# → toggle "SampleVueSlot" ON in the Filament Addons admin page
php artisan addons:relink    # symlink public/ → public/ext/samplevueslot/
```

`module.json` ships with `"active": 0` — addons install **disabled**. Once
enabled, the ServiceProvider boots, registers the slot entry, and the My Bids
table shows the `◉ ACARS` control on every bid row.

---

## Disable-safety

Everything this addon contributes happens inside the ServiceProvider's `boot()`
(the single `Skylight::slots()->register(...)` call). A phpVMS addon's provider
**only boots when the addon is enabled**. Disabled → the slot entry is never
registered → `PvSlot` renders nothing for this addon → the component code never
runs. There is no runtime flag to check and no dead entry to clean up.

---

## File tree

```
modules/SampleVueSlot/
├── README.md
├── composer.json                 # phpvms/sample-vue-slot, psr-4, laravel provider
├── module.json                   # active:0, provider listed
├── Providers/
│   └── SampleVueSlotServiceProvider.php   # boot() → Skylight::slots()->register(...)
├── ui/
│   ├── SampleBidsSlot.vue         # the per-row Vue control (imports only 'vue')
│   └── vite.config.ts             # preset build → public/widgets/slot.js
└── public/
    └── widgets/
        └── slot.js                # built ESM module (vue externalized)
```
