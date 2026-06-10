<?php

declare(strict_types=1);

namespace App\Addons\Services;

use App\Addons\Models\AddonBootCache;
use App\Addons\Models\AddonManifest;
use App\Addons\Support\BootCache;
use App\Addons\Support\ManifestParser;
use App\Models\Addon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reconciliation core: scans the configured addon location, upserts each
 * discovered addon into the addons table, and regenerates the enabled-only
 * boot cache.
 *
 * Design invariants (D-09 through D-15):
 *  - Idempotent: running multiple times produces the same DB state (D-14).
 *  - Enabled-flag preservation: operator disabled rows keep enabled=false (D-12).
 *  - Enabled-only cache: disabled addons are absent from the boot cache (D-13).
 *  - Malformed manifests: skipped with a warning; prime continues (D-15).
 *  - Cache-freshness gate: primeIfNeeded() runs when cache is absent or stale-schema (D2-09).
 *
 * Stateless and Octane-safe: no mutable instance properties.
 */
class AddonDiscoveryService
{
    public function __construct(
        private readonly ManifestParser $parser,
        private readonly BootCache $bootCache,
    ) {}

    /**
     * Reconcile addon state and (re)write the boot cache.
     *
     * Two modes, gated on isInstalled():
     *
     *  - v8 addon schema present: the DB is the source of truth. Optionally
     *    discover newly dropped-in addons (inserted disabled, D-12) when
     *    scan_for_new_on_boot is enabled, then reproject the enabled-only boot
     *    cache from the DB. This is the self-heal path — a deleted or
     *    stale-schema cache is rebuilt on the next boot.
     *
     *  - v8 addon schema absent (fresh install / v7 upgrade pre-migrate): the DB
     *    cannot be the source of truth, so the cache is bootstrapped directly from
     *    disk with every discovered addon enabled, allowing bundled modules to
     *    load before the installer runs.
     */
    public function run(): void
    {
        if ($this->isInstalled()) {
            if (config('addons.scan_for_new_on_boot')) {
                $this->discoverNewAddons();
            }

            $this->rebuildCache();

            return;
        }

        $manifests = $this->scanLocation(config('addons.paths.base'));
        /** @var list<AddonBootCache> $rows */
        $rows = [];

        foreach ($manifests as $m) {
            $rows[] = $this->buildBootCacheRow($m, true);
        }

        $this->bootCache->write($rows);
    }

    /**
     * Whether the v8 addon-engine schema is present.
     *
     * Probes for the `registry_id` column — new in v8 — rather than mere table
     * existence. A v7 install has no addons table (and no registry_id), and run()
     * executes during web-request boot (AddonServiceProvider::boot), so an
     * unguarded query/upsert before `migrate` would fatal every request.
     * Returning false routes run() to the DB-free disk bootstrap, which self-heals
     * once migrated. A connection failure is swallowed and reported as not-installed.
     */
    private function isInstalled(): bool
    {
        try {
            return Schema::hasColumn('addons', 'registry_id');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Run the prime only when the boot cache is absent or has a stale schema (D2-09).
     *
     * @return bool True when the prime was executed; false when cache was already fresh.
     */
    public function primeIfNeeded(): bool
    {
        if ($this->bootCache->isFresh()) {
            return false;
        }

        $this->run();

        return true;
    }

    /**
     * Discover new addons and update the boot cache.
     *
     * This method scans the predefined addon locations to discover new addons
     * on disk. Detected addons are cross-referenced with the database to determine
     * their enabled status.
     *
     * - Addons with a `registry_id`:
     *   - If found in the database, the enabled state is inherited.
     *   - If not found, the addon is considered new and disabled by default.
     *
     * - Addons without a `registry_id` (legacy):
     *   - If found in the database (searched by path), the enabled state is inherited.
     *   - If not found, the addon is treated as new and disabled by default.
     *
     * Newly discovered addons are upserted into the database where necessary.
     * They're *not* inserted into the boot cache until they're installed
     *
     * Important: This also writes the newly discovered addons into the database
     * but does not regenerate the boot cache. The boot cache is only regenerated
     * when the plug-in is installed.
     *
     * @return Collection<Addon>
     */
    public function discoverNewAddons(): Collection
    {
        $newAddons = collect();

        $manifests = $this->scanLocation(config('addons.paths.base'));
        if ($manifests === []) {
            return $newAddons;
        }

        /** @var Collection<Addon> $installedAddons */
        $installedAddons = Addon::query()->get();

        /** @var AddonManifest $m */
        foreach ($manifests as $m) {
            // If the addon is already in the DB, don't do anything with it.
            // Guard registry_id (nullable for unmanaged addons) so two
            // null-registry rows don't collide via null === null.
            $installed = $installedAddons->first(fn (Addon $addon): bool => ($m->registryId !== null && $addon->registry_id === $m->registryId)
                || ($addon->name === $m->name)
                || ($addon->namespace === $m->namespace));

            if ($installed) {
                continue;
            }

            $newAddons->push($this->upsert($m));
        }

        return $newAddons;
    }

    /**
     * Regenerate the boot cache from current DB enabled state.
     *
     * Scans manifests on disk, matches each to its DB row, and writes only the
     * enabled addons (enabled-only cache invariant, D-13). This is the cache
     * regeneration that lifecycle mutations (enable/disable/delete/install/update)
     * must call — run() does NOT rewrite the cache on an installed system.
     */
    public function rebuildCache(): void
    {
        $manifests = $this->scanLocation(config('addons.paths.base'));

        /** @var Collection<int, Addon> $installed */
        $installed = Addon::where('enabled', true)->get();

        /** @var list<AddonBootCache> $cacheRows */
        $cacheRows = [];

        foreach ($manifests as $m) {
            $addon = $installed->first(fn (Addon $a): bool => ($m->registryId !== null && $a->registry_id === $m->registryId)
                || $a->name === $m->name
                || $a->namespace === $m->namespace);

            if ($addon !== null) {
                $cacheRows[] = $this->buildBootCacheRow($m, true);
            }
        }

        $this->bootCache->write($cacheRows);
    }

    /**
     * Enumerate immediate subdirectories of $dir and parse each manifest.
     *
     * Returns an empty array when the directory does not exist; modules
     * may be absent on a fresh install (LOAD-01).
     *
     * Path-traversal guard (T-04-03): resolved realpath must stay within the
     * scanned base dir; suspicious directories are skipped and logged.
     *
     * @return AddonManifest[]
     */
    private function scanLocation(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $realBase = realpath($dir);

        if ($realBase === false) {
            return [];
        }

        $results = [];

        foreach (File::directories($dir) as $subDir) {
            $resolved = realpath($subDir);

            // T-04-03: skip if the resolved path escapes the base directory.
            if ($resolved === false || !str_starts_with($resolved, $realBase.DIRECTORY_SEPARATOR)) {
                Log::warning(sprintf("AddonRuntimeService: skipping '%s' — path traversal guard triggered", $subDir));

                continue;
            }

            $manifest = $this->parser->parse($resolved);

            if (!$manifest instanceof AddonManifest) {
                Log::warning(sprintf("AddonRuntimeService: skipping '%s' — module.json is missing or invalid", $resolved));

                continue;
            }

            $results[] = $manifest;
        }

        return $results;
    }

    /**
     * Build an AddonCacheEntry from a manifest and an explicit enabled flag.
     */
    private function buildBootCacheRow(AddonManifest $m, bool $enabled): AddonBootCache
    {
        return new AddonBootCache(
            name: $m->name,
            alias: $m->alias,
            type: $m->type,
            registryId: $m->registryId,
            version: $m->version,
            namespace: $m->namespace,
            providers: $m->providers,
            path: $m->path,
            autoloadPath: $m->autoloadPath,
            layout: $m->layout,
            description: $m->description,
            enabled: $enabled,
            filament: $this->probeFilament($m),
        );
    }

    /**
     * Upsert one addon row into the DB, preserving the operator's enabled flag (D-12).
     *
     * Match key (stable identity):
     *  - registry_id when managed (non-null registryId).
     *  - namespace when bundled (null registryId).
     *
     * Resolves the existing row by its match key before writing, so re-priming
     * (or a retried/interrupted scan) updates in place instead of inserting a
     * duplicate. The enabled flag + installed_at are written only when the row
     * is genuinely new, so an operator-disabled row keeps enabled=false (D-12).
     */
    private function upsert(AddonManifest $m): Addon
    {
        $match = $m->registryId !== null
            ? ['registry_id' => $m->registryId]
            : ['namespace' => $m->namespace];

        $addon = Addon::firstOrNew($match);

        $addon->name = $m->name;
        $addon->namespace = $m->namespace;
        $addon->type = $m->type;
        $addon->version = $m->version;
        $addon->registry_id = $m->registryId;
        $addon->path = $m->path;

        if (!$addon->exists) {
            $addon->enabled = false;
            $addon->installed_at = now();
        }

        $addon->save();

        return $addon;
    }

    /**
     * Probe convention-based Filament directories for a given addon.
     *
     * Filament base dir is derived from the already-computed autoloadPath:
     *  - 'root' layout: autoloadPath = addon dir  → {autoloadPath}/Filament
     *  - 'app'  layout: autoloadPath = addon/app   → {autoloadPath}/Filament
     *
     * Probes for Resources, Pages, Widgets under both 'admin' and 'system' panels.
     * Only includes subdirs that actually exist on disk.
     *
     * @return array<string, array<string, string>> panel => component => absolute path
     */
    private function probeFilament(AddonManifest $m): array
    {
        $filamentBase = $m->autoloadPath.'/Filament';

        $subdirs = ['Resources', 'Pages', 'Widgets'];
        $result = [];

        // Admin panel: {filamentBase}/{subdir}
        $adminPaths = [];

        foreach ($subdirs as $sub) {
            $absPath = $filamentBase.'/'.$sub;

            if (is_dir($absPath)) {
                $real = realpath($absPath);
                $adminPaths[$sub] = $real !== false ? $real : $absPath;
            }
        }

        if ($adminPaths !== []) {
            $result['admin'] = $adminPaths;
        }

        // System panel: {filamentBase}/System/{subdir}
        $systemPaths = [];

        foreach ($subdirs as $sub) {
            $absPath = $filamentBase.'/System/'.$sub;

            if (is_dir($absPath)) {
                $real = realpath($absPath);
                $systemPaths[$sub] = $real !== false ? $real : $absPath;
            }
        }

        if ($systemPaths !== []) {
            $result['system'] = $systemPaths;
        }

        return $result;
    }
}
