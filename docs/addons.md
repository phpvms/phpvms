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
