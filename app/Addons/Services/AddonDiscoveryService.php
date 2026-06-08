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

/**
 * Reconciliation core: scans both addon locations, upserts each discovered
 * addon into the addons table, and regenerates the enabled-only boot cache.
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
     * Scan all addon locations and write the boot cache for a fresh install,
     * and they're all marked as enabled
     *
     * If not a fresh install, upsert any newly found addons into the database,
     * and set enabled = false, and also write the boot cache
     *
     * TODO: Should any new addons only be upserted and not written into the cache?
     *    I think at some point, we shouldn't write disabled addons in the cache
     *    For now, we'll just write them all to make testing easier
     */
    public function run(): void
    {

        $manifests = $this->scanLocation(config('addons.paths.base'));
        /** @var list<AddonBootCache> $rows */
        $rows = [];

        // Scenario 1: fresh install, all found addons are enabled
        if (!installed()) {
            foreach ($manifests as $m) {
                $rows[] = $this->buildBootCacheRow($m, true);
            }

            $this->bootCache->write($rows);

            return;
        }

        /**
         * Scenario 2:
         *   This is an existing install
         *   Find any new addons that have popped up, set them to disabled
         *   Upsert them into the DB
         */

        // This should be disabled by default, and only run the new
        // addon discovery when you go to the admin panel for addons
        if (!config('addons.scan_for_new_on_boot')) {
            return;
        }

        $this->discoverNewAddons();
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
            // If the addon is already in the DB, don't do anything with it
            $installed = $installedAddons->first(fn (Addon $addon, int $key): bool => ($addon->registry_id === $m->registryId)
                || ($addon->name === $m->name)
                || ($addon->namespace === $m->namespace));

            if ($installed) {
                continue;
            }

            $newAddons->push($this->upsert($m, isNew: true));
        }

        return $newAddons;
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
     * Match key:
     *  - registry_id when managed (non-null registryId).
     *  - path when bundled (null registryId).
     *
     * Uses firstOrNew + save so enabled is set only on row creation; an existing
     * operator-disabled row keeps enabled=false across re-prime (D-12).
     */
    private function upsert(AddonManifest $m, bool $isNew): Addon
    {
        $addon = Addon::fromManifest($m);

        $addon->name = $m->name;
        $addon->namespace = $m->namespace;
        $addon->type = $m->type;
        $addon->version = $m->version;
        $addon->registry_id = $m->registryId;
        $addon->path = $m->path;

        if ($isNew) {
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
