<?php

declare(strict_types=1);

namespace App\Addons\Compat;

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use App\Addons\Support\ManifestParser;
use App\Models\Addon;
use Illuminate\Support\Collection;

/**
 * Compatibility repository satisfying the duck-typed surface of the nwidart
 * Module facade (container key: 'modules').
 *
 * Backed by AddonRuntime + ManifestParser; stateless and Octane-safe.
 * Do NOT bind this to the 'modules' key while nwidart is still active —
 * the binding is deferred to a later cutover task (Phase 8).
 */
class ModuleRepository
{
    public function __construct(
        private readonly BootCache $registry,
        private readonly ManifestParser $parser,
    ) {}

    /**
     * Return all addon shims keyed by module name.
     *
     * @return Collection<string, Module>
     */
    public function all(): Collection
    {
        return $this->registry->all()
            ->mapWithKeys(function (AddonBootCache $runtime): array {
                $addon = Addon::fromBootCache($runtime);
                $shim = $this->resolveShim($addon);

                return [$shim->getName() => $shim];
            });
    }

    /**
     * Return enabled addon shims keyed by module name.
     *
     * NOTE: reads from DB + parses manifests (cold/admin path). A future
     * optimisation could build from AddonRuntime::enabled() (boot cache)
     * once ModuleShim can be constructed from a cache row, avoiding the
     * per-row manifest parse entirely.
     *
     * @return Collection<string, Module>
     */
    public function allEnabled(): Collection
    {
        // Intentionally reads from DB (addons table), not the boot cache — the cache may
        // be absent during installer/migration flows where DB is the only source of truth.
        return $this->registry->enabled()
            ->mapWithKeys(function (AddonBootCache $runtime): array {
                $addon = Addon::fromBootCache($runtime);
                $shim = $this->resolveShim($addon);

                return [$shim->getName() => $shim];
            });
    }

    /**
     * Find a module shim by name; returns null when not found.
     *
     * Matches by manifest name (case-sensitive), falling back to basename(path).
     * Iteration order follows Eloquent default (no explicit ORDER BY).
     */
    public function find(string $name): ?Module
    {
        /** @var AddonBootCache $runtime */
        foreach ($this->registry->all() as $runtime) {
            $addon = Addon::fromBootCache($runtime);
            $shim = $this->resolveShim($addon);

            if ($shim->getName() === $name) {
                return $shim;
            }
        }

        return null;
    }

    /**
     * Check whether a module is enabled by name.
     */
    public function isEnabled(string $name): bool
    {
        return $this->find($name)?->isEnabled() ?? false;
    }

    /**
     * Return config values for the nwidart module configuration surface.
     *
     * Supports: 'namespace' → 'Modules'; all other keys return $default.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        if ($key === 'namespace') {
            return 'Modules';
        }

        return $default;
    }

    /**
     * Build a ModuleShim for the given Addon row.
     */
    private function resolveShim(Addon $addon): Module
    {
        return new Module($addon, $this->parser);
    }
}
