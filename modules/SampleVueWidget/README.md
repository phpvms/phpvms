# Sample Vue Widget (`phpvms/sample-vue-widget`)

A **third-party-style, copy-me reference addon** that ships a **pre-built Vue
widget as an ESM module** to the **skylight SPA**, and **owns its own data
endpoint**. This is the template to copy when your dashboard widget needs rich
client-side interactivity that a server-rendered fragment can't give you.

If you want to put an interactive Vue component on the pilot dashboard from an
addon repo — without any access to phpVMS's core front-end build — start here.

---

## What tier is this?

**Tier 2 — third-party Vue widget.** Your addon ships a **pre-built ESM module**
that the SPA `import()`s at runtime by URL. The module's default export is the
Vue component; the SPA renders it inside a widget frame
(`resources/skylight/apps/spa/components/widgets/resolve.ts`).

Compare with **Tier 1 — Blade widget** (`modules/SampleBladeWidget`): a
server-rendered fragment, no build step. Reach for Tier 1 when your logic is
server-side and you want zero toolchain; reach for **Tier 2** (this addon) when
you need real client-side interactivity.

See also the draft guide `site/drafts/extending-the-spa-theme.mdx`.

---

## The shared-Vue / ESM / import-map model (the important part)

The widget is built as a **Vue library in ESM format with `vue` EXTERNALIZED**.
That single decision is what makes third-party Vue widgets safe:

- The built `sample.js` does **not** bundle Vue. It contains a bare
  `import { ... } from "vue"`.
- The SPA shell emits an **import-map** (see
  `resources/views/layouts/<theme>/spa.blade.php`) that maps the bare `"vue"`
  specifier to the host app's **single, shared** Vue vendor chunk.
- So the host and every addon widget run on **one** Vue instance — one
  reactivity system, one runtime. No duplicate Vue, no version fork, no "two
  Vues" bugs.

If a shared design-system package (`@skylight/ui`) ships later, it is
externalized the same way — add it to `external` in the preset and to the shell
import-map.

---

## The files that matter

| File | Role |
| --- | --- |
| `skylight/SampleVueWidget.vue` | The widget UI. Imports only from `vue`. Fetches this addon's endpoint; shows loading / success / fail-visible error. Styled with the host's `--pv-*` tokens. |
| `skylight/vite.config.ts` | Build config. Imports the shared widget preset and outputs `public/widgets/sample.js`. |
| `Http/Data/SamplePingData.php` | `spatie/laravel-data` `Data` object = the endpoint's typed JSON shape. |
| `Http/Controllers/SamplePingController.php` | `show(): SamplePingData` — the addon's own data endpoint. |
| `Providers/SampleVueWidgetServiceProvider.php` | `boot()` registers the route **and** the widget. The entire integration surface. |
| `module.json` / `composer.json` | Addon identity. Ships `"active": 0` (installs disabled). |

The addon owns **both** its UI (the `.vue`) and its data (the endpoint). Disable
the addon and *neither* runs — proof lives in the widget's own comments.

---

## How to build the widget

The build is standalone Vite; it does not need the app running.

From the theme workspace (`resources/skylight/`):

```bash
# Build the core theme AND discover every addon widget config:
pnpm build

# ...or build ONLY the addon widgets (skips the core app build):
pnpm build:addons
```

Discovery is automatic: `scripts/build-addons.mjs` globs for
`modules/*/skylight/vite.config.{ts,mjs,js}` and runs `vite build` on each. This
addon's config points its `sample` entry at `SampleVueWidget.vue` and writes:

```
modules/SampleVueWidget/public/widgets/sample.js
```

To build just this one addon directly:

```bash
# from resources/skylight/
pnpm exec vite build -c ../../modules/SampleVueWidget/skylight/vite.config.ts
```

Verify it externalized Vue (should print a line like `import { ... } from "vue"`):

```bash
grep 'from "vue"' modules/SampleVueWidget/public/widgets/sample.js
```

---

## From `public/` to `/ext/…` (why `addons:relink`)

Built assets land in the addon's `public/` dir. An enabled addon's `public/` is
**symlinked** to `public/ext/<lower-name>/`, so the widget is web-served at:

```
/ext/samplevuewidget/widgets/sample.js
```

…which is exactly the `module` URL the ServiceProvider registers. After you
enable the addon, run:

```bash
php artisan addons:relink
```

to (re)create that symlink. (Build output under `public/widgets/` is a build
artifact; commit it or rebuild on deploy per your addon's conventions.)

---

## Enabling it (a human does this)

```bash
php artisan addons:prime      # discover the module
# enable "SampleVueWidget" in the Filament Addons page
php artisan addons:relink     # symlink public/ -> public/ext/samplevuewidget/
```

Then add **"Sample Vue widget"** from the dashboard's Add-widget menu.

---

## Disable-safety

The only place this addon touches the host is inside `boot()`:
`Route::get(...)` for the endpoint and `Skylight::widgets()->register([...])`
for the widget. A phpVMS addon's ServiceProvider boots **only when the addon is
enabled**. Disable it and:

- the widget never enters the SPA catalog, and
- the `/api/sample-vue/ping` route never exists.

There is no runtime `enabled` flag to check and nothing to clean up — it's a
property of *where* we register (`boot()`), not of any flag. See
`app/Support/Skylight/WidgetRegistry.php` for the same guarantee.

---

## Generating the TypeScript type from the Data object (optional)

`SamplePingData`'s public properties are the response contract. The widget
mirrors them by hand in a `PingSuccess` interface, but you can instead **generate**
the matching TS type with `spatie/typescript-transformer`
(`php artisan typescript:transform`) to keep client and server in lock-step.
