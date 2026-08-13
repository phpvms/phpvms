# Sample Blade Widget (`phpvms/sample-blade-widget`)

A **first-party, copy-me reference addon** that adds a native-looking dashboard
widget to the **skylight SPA** using nothing but a controller and a Blade
fragment. No Vue. No front-end build. No compiled assets.

If you want an addon to put something on the pilot dashboard and you already
think in Blade + PHP, start here.

---

## What tier is this?

**Tier 1 — Blade widget.** The widget's UI is a server-rendered Blade fragment.
The SPA's generic host shell
(`resources/js/apps/fe-vue/src/components/widgets/BladeWidget.vue`) fetches your
endpoint over a credentialed request, injects the returned HTML into the
dashboard, and progressively enhances any `<form>` inside it. Your addon's logic
runs entirely on the server; only rendered HTML reaches the browser.

Compare with **Tier 2 — Vue widget**, where you ship a pre-built ESM module the
SPA imports at runtime. Reach for Tier 1 when your logic is server-side and you
don't want a build toolchain; reach for Tier 2 when you need rich client-side
interactivity.

---

## The three files that matter

| File | Role |
| --- | --- |
| `Providers/SampleBladeWidgetServiceProvider.php` | Registers views, the route, and the widget with the skylight hub — **the entire SPA integration surface**. |
| `Http/Controllers/NotamsController.php` | The "hidden server logic": builds the data and returns a layout-less Blade fragment. |
| `Resources/views/notams.blade.php` | The layout-less Blade fragment the shell injects into the dashboard. |

Supporting files: `module.json` (addon manifest), `composer.json` (PSR-4 +
Laravel provider discovery), `Http/Routes/web.php` (the one route).

---

## How registration works

Everything the SPA needs is one call, made from the provider's `boot()`:

```php
use App\Support\Skylight\Facades\Skylight;

Skylight::widgets()->register([
    'id'          => 'sample-blade-notams',
    'kind'        => 'blade',
    'mode'        => 'island',
    'title'       => 'Station NOTAMs (sample)',
    'icon'        => 'alert-triangle',               // Tabler icon name
    'endpoint'    => '/widgets/sample-notams',      // LITERAL path — not route()
    'defaultZone' => 'sidebar',
    'defaultOn'   => false,
]);
```

> **Why a literal `endpoint` path, not `route('sample-blade-widget.notams')`?**
> Addon ServiceProviders boot early and route-registration order across
> providers is not guaranteed, so calling `route()` by name at boot can throw
> `RouteNotFoundException`. The `endpoint` is just a serialized string in the
> shared props, so a literal path is both simpler and safe. (The Blade
> fragment's `<form action>` may still use `route()` — that renders at REQUEST
> time, when all routes exist.)

The `WidgetRegistry` is serialised into the Inertia shared props each request,
so the Vue catalog is built from whatever is registered at that moment. The full
list of recognised keys is documented on
`App\Support\Skylight\WidgetRegistry::register()` — read that docblock.

Three things must stay in sync — the route's **path** is the single source of
truth:

1. the route path in `Http/Routes/web.php` → `/widgets/sample-notams`
2. the `endpoint` in the provider → the literal `'/widgets/sample-notams'`
3. the `<form action>` in the fragment → the same path (rendered at request
   time via `route('sample-blade-widget.notams')`, which resolves to it)

---

## How disable-safety works

You do **not** write an "is this addon enabled?" check anywhere.

A phpVMS addon's `ServiceProvider` only boots when the addon is **enabled**. We
register the widget from `boot()`, so:

- **Enabled** → `boot()` runs → the widget is in the registry → it appears in the
  dashboard catalog, and its route/endpoint exist.
- **Disabled** → `boot()` never runs → nothing registered → no catalog entry, no
  route, no dead endpoint to guard.

Disable-safety is a property of *where* you register (inside `boot()`), not of any
flag you toggle at runtime. The same guarantee is documented on
`App\Support\Skylight\WidgetRegistry`.

---

## Island vs iframe

`kind: 'blade'` widgets are hosted by `BladeWidget.vue` in one of two modes:

- **`island` (used here, the default).** The shell fetches your endpoint, injects
  the HTML, and intercepts any `<form>` submit: it serialises the fields, adds the
  `X-CSRF-TOKEN` header, re-fetches your endpoint, and swaps the returned HTML in
  place — no full page reload. You write a **plain `<form>`**; the shell wires up
  the AJAX. Best for content that should feel part of the dashboard.
- **`iframe`.** The shell renders a same-origin `<iframe>` pointing at your
  endpoint and auto-sizes it. Native forms, native scripts, full isolation. Best
  when you need a self-contained mini-page.

Because we use island mode, the NOTAM lookup `<form method="get">` in the fragment
just works: type an ICAO, submit, and the shell re-fetches and swaps the list.

---

## Logic stays server-side (the Blade advantage)

`NotamsController::show()` builds its data on the server and returns only rendered
HTML. In this sample the data is a small fake in-memory list, but in a real addon
this could be a NOTAM API call, a DB query, or per-airline business rules — none
of which ship to the browser as code or become tamperable by the client. If your
value is in the logic, Tier 1 keeps it where it belongs.

The fragment is **layout-less** on purpose: no `@extends`, no `<html>`. The shell
injects it into an element that already exists on the dashboard.

---

## How to enable it

Addon installs are disabled by default, so `module.json` ships with `"active": 0`.
A human turns it on:

```bash
php artisan addons:prime
```

Then open the **Addons** page in the phpVMS admin (Filament) and enable
**SampleBladeWidget**. Once enabled, its provider boots, the widget registers, and
"Station NOTAMs (sample)" shows up in the dashboard's **Add widget** menu (it is
`defaultOn: false`, so a pilot adds it explicitly).

> Do not commit `module.json` with `"active": 1` — keep addon installs opt-in.

---

## Copy this addon

1. Copy the `modules/phpvms-sample-blade-widget/` folder to `modules/YourWidget/`.
2. Rename the namespace `Modules\SampleBladeWidget\` → `Modules\YourWidget\` in
   every PHP file, `composer.json`, and `module.json`.
3. Change the widget `id`, `title`, `icon`, route name, and view namespace.
4. Replace the fake data in the controller with your real (server-side) logic.
5. Restyle the fragment with `--pv-*` tokens so it stays native to skylight.

---

## Reference

See the full extension contract: `site/drafts/extending-the-spa-theme.mdx`.
