<?php

declare(strict_types=1);

namespace App\Providers;

use App\Addons\AddonLoader;
use App\Addons\AddonRegistry;
use App\Addons\AutoloadGuard;
use App\Addons\BootCache;
use App\Addons\Compat\ModuleRepository;
use App\Addons\Filament\FilamentPanelExtender;
use App\Addons\ManifestParser;
use App\Addons\PrimeService;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Phase 2 addon engine into application boot.
 *
 * register() handles all engine wiring so that addon service providers are
 * registered before Filament panels resolve (D2-10):
 *  1. Binds all engine singletons (Octane-safe: stateless services).
 *  2. Binds the 'modules' container key to ModuleRepository, making the
 *     nwidart Module facade route to our shim (nwidart provider retired).
 *  3. At non-console boot: runs the classmap-authoritative guard (LOAD-08),
 *     then auto-primes the boot cache if absent/stale (D2-09).
 *     Skipped in console (D-17) to avoid blocking migrate/install/tests.
 *  4. Runs the AddonLoader in all contexts so artisan sees addon
 *     commands and migrations (D2-11). No-ops on empty cache.
 *  5. Hooks FilamentPanelExtender into beforeResolving('filament', ...)
 *     so addon Filament discovery is applied before panels resolve (D2-07).
 *
 * boot() is intentionally empty — all wiring belongs in register().
 */
class AddonServiceProvider extends ServiceProvider
{
    /**
     * Bind engine services and run loader + Filament hook.
     *
     * All singletons are Octane-safe: no mutable instance properties.
     */
    #[\Override]
    public function register(): void
    {
        // ── Phase 1 singletons ──────────────────────────────────────────────
        $this->app->singleton(BootCache::class);
        $this->app->singleton(ManifestParser::class);
        $this->app->singleton(AutoloadGuard::class);
        $this->app->singleton(AddonRegistry::class);
        $this->app->singleton(PrimeService::class);

        // ── Phase 2 singletons ──────────────────────────────────────────────
        $this->app->singleton(AddonLoader::class);
        $this->app->singleton(FilamentPanelExtender::class);
        $this->app->singleton(ModuleRepository::class);

        // Bind the nwidart 'modules' container key to our shim so that
        // Nwidart\Modules\Facades\Module resolves to ModuleRepository.
        $this->app->singleton('modules', fn ($app) => $app->make(ModuleRepository::class));

        // ── Non-console: guard + auto-prime ─────────────────────────────────
        // D-17: skip in console contexts (migrate/install/tests).
        if (!$this->app->runningInConsole()) {
            // LOAD-08 provider-level guard: halts boot before any runtime addPsr4 when
            // classmap-authoritative is active. Intentionally duplicated — the loader's
            // internal guard re-checks the resolved ClassLoader instance; this provider-level
            // call is the boot-halt fence that fires BEFORE the loader runs at all.
            $this->app->make(AutoloadGuard::class)->assertRuntimeAutoloadSupported();

            // D2-09: auto-prime when cache is absent or has a stale schema.
            $this->app->make(PrimeService::class)->primeIfNeeded();
        }

        // ── Loader: runs in ALL contexts (D2-11) ────────────────────────────
        // Registers addon PSR-4 namespaces + service providers from the boot
        // cache. Empty cache → no-op. Console needs this for commands/migrations.
        // The loader contains its own guard call (re-checks the resolved ClassLoader);
        // both guards are intentional — see provider-level comment above (LOAD-08).
        $this->app->make(AddonLoader::class)->register($this->app);

        // ── Filament hook (D2-07) ────────────────────────────────────────────
        // Apply addon Filament discovery paths before panels resolve.
        $this->app->beforeResolving('filament', function (): void {
            $this->app->make(FilamentPanelExtender::class)->apply();
        });
    }
}
