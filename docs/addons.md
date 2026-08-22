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
text while the rest of the page renders normally. (It cannot be rendered from inside the page
view: Laravel's `View::render()` calls `Factory::flushState()` when a view throws, which wipes the
global component stack and would take the whole page down.)

Panels render eagerly with the page, like the built-in tabs. If your content is expensive, defer
inside your own view — render a mount point and load it on demand.

Closures are not serializable; the registry is an in-memory, boot-time singleton and is never
cached.

### Frontend: the `pireps.detail.main` slot

The skylight SPA's PIREP detail layout exposes a Skylight slot with the pirep as context:

```php
Skylight::slots()->register([
    'slot'      => 'pireps.detail.main',
    'component' => 'AcarsDebriefPanel',
    'module'    => '/ext/vmsacars/widgets/debrief.js',
    'props'     => ['pirep' => '@pirep'],   // @-ref resolved from the slot context
]);
```

It sits full-width below the two-column detail grid, and renders nothing at all when no addon
targets it. See `modules/phpvms-sample-vue-slot/` for a complete working addon (registration,
the Vue component, and the ESM build step).
