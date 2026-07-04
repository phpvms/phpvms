# phpVMS Dashboard (`phpvms/phpvms-dashboard`)

The **first-party home for skylight dashboard widgets.** This addon ships the
**weather (METAR) widget** as a **pre-built Vue (ESM) widget** that **owns its
own data endpoint** — and it rides the exact same addon path as a third-party
widget (compare `modules/SampleVueWidget`).

The weather widget used to be bundled directly into the skylight SPA
(`WxWidget.vue`, registered in the front-end catalog). Moving it here proves the
migration path: even a core-authored widget can live in an addon, loaded at
runtime by URL, with **zero** access to the core front-end build required.

---

## What tier is this?

**Tier 2 — Vue widget.** The addon ships a **pre-built ESM module** that the SPA
`import()`s at runtime by URL. The module's default export is the Vue component;
the SPA renders it inside a widget frame
(`resources/skylight/apps/spa/components/widgets/resolve.ts`).

The widget is built as a **Vue library in ESM format with `vue` EXTERNALIZED**,
so the host and every addon widget run on **one** shared Vue instance via the
import-map the SPA shell emits. See `modules/SampleVueWidget/README.md` for the
full shared-Vue / import-map explanation — this addon follows the same model.

---

## The two things this addon owns

### 1. The widget UI + its endpoint

The addon owns **both** its UI (`ui/WeatherWidget.vue`) and the API it
talks to (`Http/Controllers/WeatherController.php`). The widget fetches this
addon's **own** endpoint:

```
GET /api/phpvms-dashboard/weather/{icao}
```

…which delegates to the core `App\Services\AirportService` (the same service the
core `/api/weather/{icao}` endpoint uses) and returns a `WeatherData` DTO. The
core `/api/weather/{icao}` endpoint is left untouched — it is still used
elsewhere (e.g. the neutral-floor `<weather-widget>` custom element).

### 2. How the widget gets the live station (the important bit)

An ESM addon widget **must not import inertia**, so it can't call `usePage()` to
read the current airport. Instead the widget takes a plain **`icao` prop**, and
the addon registers it with a **page-DTO ref**:

```php
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
```

`Dashboard.vue` resolves any `@`-prefixed widget prop against the live page DTO
props **before binding** — exactly like the slot registry resolves slot props
(reusing `resolveValue()` from `apps/spa/lib/registry.ts`). So
`'@currentAirport'` becomes the pilot's current station, and the widget receives
it as a normal `icao` prop with no host imports.

---

## The files that matter

| File | Role |
| --- | --- |
| `ui/WeatherWidget.vue` | The widget UI. Imports only from `vue`. Takes an `icao` prop, fetches this addon's endpoint; loading / success / fail-visible error; `.wx` root + `--pv-*` tokens. |
| `ui/vite.config.ts` | Build config. Imports the shared widget preset; outputs `public/widgets/weather.js`. |
| `Http/Data/WeatherData.php` | `spatie/laravel-data` `Data` = the endpoint's typed success JSON shape. |
| `Http/Controllers/WeatherController.php` | `show(icao)` — delegates to core `AirportService`, mirrors the core weather JSON. |
| `Providers/PhpvmsDashboardServiceProvider.php` | `boot()` registers the route **and** the widget. The entire integration surface. |
| `module.json` / `composer.json` | Addon identity. Ships `"active": 0` (installs disabled). Providers are listed in `module.json`. |

---

## How to build the widget

The build is standalone Vite; it does not need the app running. From the theme
workspace (`resources/skylight/`):

```bash
# Build the core theme AND discover every addon widget config:
pnpm build

# ...or build ONLY the addon widgets (skips the core app build):
pnpm build:addons
```

Discovery is automatic: `scripts/build-addons.mjs` globs for
`modules/*/ui/vite.config.{ts,mjs,js}` and runs `vite build` on each. This
addon's `weather` entry points at `WeatherWidget.vue` and writes:

```
modules/PhpvmsDashboard/public/widgets/weather.js
```

Verify it externalized Vue:

```bash
grep 'from "vue"' modules/PhpvmsDashboard/public/widgets/weather.js
```

---

## Enabling it (a human does this)

Built assets land in the addon's `public/` dir, which an **enabled** addon
symlinks to `public/ext/phpvmsdashboard/` — so the widget is web-served at
`/ext/phpvmsdashboard/widgets/weather.js` (exactly the `module` URL the provider
registers; note the symlink uses `strtolower(NAME)` = `phpvmsdashboard`, **not**
the hyphenated alias).

```bash
php artisan addons:prime      # discover the module
# enable "PhpvmsDashboard" in the Filament Addons page
php artisan addons:relink     # symlink public/ -> public/ext/phpvmsdashboard/
```

The weather widget is `defaultOn`, so once enabled it appears in the dashboard's
default sidebar layout.

---

## Disable-safety

The only place this addon touches the host is inside `boot()`: the
`Route::get(...)` for the endpoint and `Skylight::widgets()->register([...])`
for the widget. A phpVMS addon's ServiceProvider boots **only when the addon is
enabled**. Disable it and:

- the weather widget never enters the SPA catalog, and
- the `/api/phpvms-dashboard/weather/{icao}` route never exists.

Because weather is now addon-provided, the skylight dashboard shows the weather
card **only when this addon is enabled**. (The existing dashboard e2e asserts
`.wx` is visible, so it requires this addon enabled to pass.)
