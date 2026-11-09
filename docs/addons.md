# Addon Implementation Documentation

## Boot Time

```text
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          BOOT TIME (per Octane worker)                           │
│                                                                                  │
│   bootstrap/providers.php → AppServiceProvider                                   │
│                                                                                  │
│   ┌────────────────────────── AddonServiceProvider ──────────────────────────┐  │
│   │                          (replaces ModulesServiceProvider)                │  │
│   │                                                                            │  │
│   │  register():                                                               │  │
│   │   1. AddonManifest::load() ← bootstrap/cache/addons.php (fast path)        │  │
│   │      └─ fallback: AddonScanner ← DB `addons` table ← scan disk             │  │
│   │   2. AddonLoader::register($manifest)                                      │  │
│   │      ├─ Composer\Autoload\ClassLoader::addPsr4(ns, path)  ← per addon      │  │
│   │      ├─ $app->register($AddonProvider::class)             ← per addon SP   │  │
│   │      └─ defer Filament resource/page/widget discover paths into $registry  │  │
│   │   3. $this->app->beforeResolving('filament', fn () => FilamentPanelExtender│  │
│   │        ::apply($registry))  ← runs once, just before AdminPanelProvider    │  │
│   └────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
│   AdminPanelProvider / SystemPanelProvider (unchanged)                           │
│   └─ ->discoverResources(in: app_path('Filament/Resources'), ...)                │
│   └─ FilamentPanelExtender appends addon discover paths to the same panel        │
│                                                                                  │
│   SocialiteProviders\Manager\ServiceProvider                                     │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## Installation

```text
┌─────────────────────────────────────────────────────────────────────────────────┐
│                       INSTALL TIME (CLI / service-layer)                         │
│                                                                                  │
│   AddonInstaller (orchestrator service)                                          │
│      │                                                                           │
│      ├── AddonSource (interface) ← LocalZipSource | UrlSource | DirSrc           │
│      │   resolves & extracts to: modules/_staging/{tmpdir}/           │
│      │                                                                           │
│      ├── AddonValidator                                                          │
│      │   ├─ zip-slip / path-traversal guard                                      │
│      │   ├─ module.json schema check (name, alias, providers, type, compat,      │
│      │   │   registry_id, version)                                               │
│      │   ├─ structure check (root-PSR-4 OR app/-PSR-4 from inner composer.json)  │
│      │   └─ VerificationHook chain (checksum/signature/compat — stubbed v1)      │
│      │                                                                           │
│      ├── AddonIsolationGuard                                                     │
│      │   ├─ static prefix check (regex AST scan of Database/migrations/*.php)    │
│      │   └─ sandbox-DB schema diff (run migrations into a temp schema, diff)     │
│      │                                                                           │
│      ├── AddonPlacer                                                             │
│      │   └─ rename(_staging, modules/{registry_id})  ← POSIX atomic              │
│      │                                                                           │
│      ├── AddonMigrator                                                           │
│      │   └─ artisan migrate --path={addonPath}/{Database,database}/migrations    │
│      │        tagged: addon_owner column on migrations table                     │
│      │                                                                           │
│      ├── AddonAssetPublisher                                                     │
│      │     └─ Storage::disk('public')->putFile('a/{registry_id}/...')            │
│      │                                                                           │
│      ├── AddonRegistry::activate()                                               │
│      │     ├─ INSERT/UPDATE `addons` row (DB = source of truth)                  │
│      │     └─ BootCache::write() → bootstrap/cache/addons.php                    │
│      │                                                                           │
│      └── Octane::reload()  ← activates new providers on next worker boot         │
└──────────────────────────────────────────────────────────────────────────────────┘
```

## PIREP view extension points

Two hooks let an addon add content to the PIREP pages without core changing again.

### Admin: a tab on the PIREP view page

`App\Support\PirepView\PirepViewTabRegistry` is a container singleton. Register from your
`ServiceProvider::boot()`; a disabled addon's provider never boots, so its tab simply never
appears. Guard with `class_exists` so the addon still installs against an older core.

```php
use App\Models\Pirep;
use App\Support\PirepView\PirepViewTabRegistry;

public function boot(): void
{
    if (!class_exists(PirepViewTabRegistry::class)) {
        return;
    }

    app(PirepViewTabRegistry::class)->register([
        'id'      => 'vmsacars.debrief',                       // required, namespaced
        'label'   => fn (Pirep $pirep): string => __('vmsacars::debrief.tab_label'),
        'badge'   => fn (Pirep $pirep): ?int => PirepDebrief::where('pirep_id', $pirep->id)->count() ?: null,
        'visible' => fn (Pirep $pirep): bool => PirepDebrief::where('pirep_id', $pirep->id)->exists(),
        'order'   => 100,
        'view'    => 'vmsacars::pirep-debrief-tab',
    ]);
}
```

| Key       | Type                                                  | Notes                                                                                                                           |
| --------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `id`      | `string`                                              | Required, namespaced `vendor.name`. Doubles as the Alpine tab value; a duplicate id **replaces** the earlier entry (last-wins). |
| `label`   | `string` or `Closure(Pirep): string`                  | Rendered as escaped text.                                                                                                       |
| `badge`   | `string`/`int` or `Closure(Pirep): string\|int\|null` | Optional. Escaped; hidden when blank.                                                                                           |
| `visible` | `Closure(Pirep): bool`                                | Optional, default true. False hides both the button and the panel for that record.                                              |
| `order`   | `int`                                                 | Optional, default 100 — i.e. after the built-in tabs. Ties keep registration order.                                             |
| `view`    | `string`                                              | Required Blade view, rendered with **only** `['record' => $pirep]`.                                                             |

The view gets the record and nothing else — not the page's `$mapFeatures`, `$logEntries` or
`$this`. It is rendered to a string by `ViewPirep::getViewData()` before the page renders, inside
a `Throwable` catch: if it throws, the exception is reported and that one panel shows fallback
text while the rest of the page renders normally. A `visible`, `label` or `badge` closure that
throws is also reported, but drops the whole tab — with no label there is nothing to put on a
button. Either way the page still renders. (The view cannot be rendered from inside the page
view: Laravel's `View::render()` calls `Factory::flushState()` when a view throws, which wipes the
global component stack and would take the whole page down.)

Panels render eagerly with the page, like the built-in tabs. If your content is expensive, defer
inside your own view — render a mount point and load it on demand.

Closures are not serializable; the registry is an in-memory, boot-time singleton and is never
cached.

### Frontend: the `pireps.detail.main` slot

The skylight SPA's PIREP detail layout exposes an extension slot (`App\Enums\Slot::PirepsDetailMain`) with the pirep as context.

```php
use App\Enums\Slot;
use App\Support\Extensions\Facades\Extensions;

Extensions::slots()->register([
    'slot'      => Slot::PirepsDetailMain,
    'component' => 'AcarsDebriefPanel',
    'module'    => '/ext/vmsacars/widgets/debrief.js',
    'props'     => ['pirep' => '@pirep'],   // @-ref resolved from the slot context
]);
```

It sits full-width below the two-column detail grid, and renders nothing at all when no addon
targets it.

## Tiles

A tile is a self-contained piece of pilot UI, rendered by a Livewire component or a Blade view, that
a theme can place on any page. An addon declares its tiles in `registerAppTiles()` on its
`AddonServiceProvider`: a list of closures that each return a `Tile`. The closures run when the registry is
first read, never at boot, and again for each locale, so `__()` in them is safe. They must not read
other request state (user, session, request):

```php
use App\Features\Tiles\Tile;
use Closure;

/** @return list<Closure(): Tile> */
public function registerAppTiles(): array
{
    return [
        fn (): Tile => Tile::livewire(
            id: 'my-addon-stats',
            component: 'my-addon::stats',
            title: __('my-addon::tiles.stats'),
            icon: 'chart-bar',
            showInDashboard: true,
            default: true,
        ),
        fn (): Tile => Tile::view(
            id: 'my-addon-news',
            view: 'my-addon::tiles.news',
            title: 'News',
            icon: 'newspaper',
            data: ['limit' => 5],
            showInDashboard: true,
            zone: Tile::ZONE_SIDEBAR,
        ),
    ];
}
```

Core and theme code that is not an addon provider calls `app(Tiles::class)->register($closure)`
from `boot()` instead.

| Field                | Notes                                                                                           |
| -------------------- | ----------------------------------------------------------------------------------------------- |
| `id`                 | Unique, lower-kebab-case.                                                                       |
| `title`              | String, may come from `__()`.                                                                   |
| `icon`               | Icon name.                                                                                      |
| `component` / `view` | Exactly one render target: a Livewire component name or a Blade view. `data` is for views only. |
| `bare`               | The tile draws its own surface.                                                                 |
| `showInDashboard`    | Offer the tile on the pilot dashboard. Default `false`.                                         |
| `zone`               | Dashboard only. `Tile::ZONE_GRID` (default) or `ZONE_SIDEBAR`.                                  |
| `span`               | Dashboard only. `1` or `2`; grid only.                                                          |
| `default`            | Dashboard only. In the default layout when true; needs `showInDashboard`.                       |
| `order`              | Dashboard only. Default-layout ordering, default 100; ties keep registration order.             |

Invalid definitions throw `InvalidArgumentException`, as does a closure that builds a tile with
another `id`. A duplicate `id` is **replaced** by the last registration, so a theme can override a
tile registered earlier. `remove($id)` hides a tile whether it is registered before or after the
call, and `has($id)` checks one without building it. A disabled addon's provider never boots, so its
tiles are absent. Core ships no tiles itself.

Themes read the merged result from `App\Features\Tiles\Tiles`: `all()`, `find($id)` and
`resolve($ids)` (ids to tiles, in order, unknown skipped) for any page. The dashboard methods see only
tiles with `showInDashboard`: `dashboard()`, `defaultLayout()`, `sanitize($layoutFromBrowser)`,
`zone($layout, $zone)` (a layout's tiles for one zone) and `available($layout)` (tiles not placed in
either zone, in registration order, for an "Add tile" menu).

### Rendering in a theme

A tile pack is a normal `module` addon: its provider (above) registers the tiles, and they disappear
when the addon is disabled. The theme draws the frame; `<x-tile>` renders only the body, with no
wrapper or classes.

```blade
@php($tiles = app(\App\Features\Tiles\Tiles::class))
@php($layout = $tiles->sanitize($saved))

@foreach ($tiles->zone($layout, 'grid') as $tile)
    <section class="{{ $tile->span === 2 ? 'col-span-2' : '' }}">
        @unless ($tile->bare)
            <h3><x-dynamic-component :component="'icon-'.$tile->icon" /> {{ $tile->title }}</h3>
        @endunless
        <x-tile :tile="$tile" :with="['route' => $route]" />
    </section>
@endforeach
```

Outside the dashboard, render one by id: `<x-tile id="weather" />`.

The tag takes `tile` (a `Tile`) or `id` (looked up in the registry), never both. When the same tile
is on a page more than once, give each an `instance` (`instance="arrival"`) so each Livewire child
gets its own key. An unknown `id`
renders nothing, or an HTML comment naming it when `app.debug` is on.

`:with` carries page data the tile needs. A Livewire tile receives only the keys its `mount()`
arguments or public properties accept, plus Livewire's own control keys such as `lazy`, so one shared
`:with` is safe; the rest are dropped instead of becoming HTML attributes. It is keyed `tile-{id}`, or `tile-{id}:{instance}`. A
view tile receives its declared `data` with `:with` merged over it, so render-time values win
(`Tile::dataWith()`).
