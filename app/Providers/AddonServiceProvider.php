<?php

declare(strict_types=1);

namespace App\Providers;

use App\Addons\AddonAutoLoader;
use App\Addons\AddonRegistry;
use App\Addons\Filament\FilamentPanelExtender;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonAssetLinker;
use App\Addons\Support\AutoloadGuard;
use App\Addons\Support\BootCache;
use App\Addons\Support\ManifestParser;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Wires the addon engine into application boot.
 *
 * register() handles all engine wiring so that addon service providers are
 * registered before Filament panels resolve (D2-10):
 *  1. Binds all engine singletons (Octane-safe: stateless services).
 *  2. At non-console boot: runs the classmap-authoritative guard (LOAD-08).
 *     Skipped in console (D-17) to avoid blocking migrate/install/tests.
 *  3. Runs the AddonLoader in all contexts so artisan sees addon
 *     commands and migrations (D2-11). No-ops on empty cache.
 *  4. Hooks FilamentPanelExtender into beforeResolving('filament', ...)
 *     so addon Filament discovery is applied before panels resolve (D2-07).
 *
 * boot() auto-primes the boot cache when absent/stale (D2-09). This is the
 * only step that queries the database, so it is deferred out of register()
 * — no DB access belongs in the register phase. The loader in register()
 * reads the file cache (DB-free), so a stale cache self-heals on the next
 * request after boot() rewrites it.
 */
class AddonServiceProvider extends ServiceProvider
{
    /**
     * Bind engine services and run loader + Filament hook.
     *
     * All singletons are Octane-safe: no mutable instance properties.
     */
    #[Override]
    public function register(): void
    {
        // ── Phase 1 singletons ──────────────────────────────────────────────
        $this->app->singleton(BootCache::class);
        $this->app->singleton(ManifestParser::class);
        $this->app->singleton(AutoloadGuard::class);
        $this->app->singleton(AddonDiscoveryService::class);

        // ── Phase 2 singletons ──────────────────────────────────────────────
        $this->app->singleton(AddonAutoLoader::class);
        $this->app->singleton(FilamentPanelExtender::class);
        $this->app->singleton(AddonAssetLinker::class, fn (): AddonAssetLinker => AddonAssetLinker::fromConfig());
        $this->app->singleton(AddonRegistry::class);

        // ── Non-console: autoload guard ─────────────────────────────────────
        // D-17: skip in console contexts (migrate/install/tests).
        if (!$this->app->runningInConsole()) {
            // LOAD-08 provider-level guard: halts boot before any runtime addPsr4 when
            // classmap-authoritative is active. Intentionally duplicated — the loader's
            // internal guard re-checks the resolved ClassLoader instance; this provider-level
            // call is the boot-halt fence that fires BEFORE the loader runs at all.
            $this->app->make(AutoloadGuard::class)->assertRuntimeAutoloadSupported();
        }

        // ── Loader: runs in ALL contexts (D2-11) ────────────────────────────
        // Registers addon PSR-4 namespaces + service providers from the boot
        // cache. Empty cache → no-op. Console needs this for commands/migrations.
        // The loader contains its own guard call (re-checks the resolved ClassLoader);
        // both guards are intentional — see provider-level comment above (LOAD-08).
        $this->app->make(AddonAutoLoader::class)->register($this->app);

        // ── Filament hook (D2-07) ────────────────────────────────────────────
        // Apply addon Filament discovery paths before panels resolve.
        $this->app->beforeResolving('filament', function (): void {
            $this->app->make(FilamentPanelExtender::class)->apply();
        });
    }

    /**
     * Auto-prime the boot cache when it is absent or has a stale schema (D2-09).
     *
     * Deferred to boot() because primeIfNeeded() reconciles the addons table —
     * the only database access in this provider — and no DB query belongs in the
     * register phase. Skipped in console (D-17) to avoid blocking migrate/install/tests.
     */
    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            $this->app->make(AddonDiscoveryService::class)->primeIfNeeded();
        }
    }
}
