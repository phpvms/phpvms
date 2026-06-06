<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Models\ManifestData;
use App\Models\Addon;
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
class PrimeService
{
    public function __construct(
        private readonly ManifestParser $parser,
        private readonly BootCache $bootCache,
    ) {}

    /**
     * Scan all addon locations and write the boot cache.
     *
     * When the application is not yet installed, all discovered addons are
     * written as enabled (scenario 1).
     *
     * When installed, the enabled flag is read from the DB (scenarios 2/3).
     * Addons present on disk but absent from the DB are upserted as disabled
     * — they require explicit operator activation.
     */
    public function run(): void
    {
        $locations = [
            base_path('modules'),
            // storage_path('app/addons'),
        ];

        /** @var ManifestData[] $manifests */
        $manifests = [];

        foreach ($locations as $baseDir) {
            foreach ($this->scanLocation($baseDir) as $manifest) {
                $manifests[] = $manifest;
            }
        }

        /** @var list<AddonRuntime> $rows */
        $rows = [];

        // Scenario 1: fresh install, all found addons are enabled
        if (!installed()) {
            foreach ($manifests as $m) {
                $rows[] = $this->buildRow($m, true);
            }

            $this->bootCache->write($rows);

            return;
        }

        // Scenario 2/3: installed, all addons are enabled or disabled

        // Build a lookup of all DB rows keyed by registry_id and path.
        $dbByRegistryId = [];
        $dbByPath = [];

        foreach (Addon::query()->get() as $addon) {
            if ($addon->registry_id !== null) {
                $dbByRegistryId[$addon->registry_id] = (bool) $addon->enabled;
            } else {
                $dbByPath[$addon->path] = (bool) $addon->enabled;
            }
        }

        /** @var ManifestData $m */
        foreach ($manifests as $m) {

            // The addon does have  a registry_id - so it's a legacy addon
            if ($m->registryId !== null) {
                // If the addon is already in the DB, use its enabled flag.
                if (array_key_exists($m->registryId, $dbByRegistryId)) {
                    $enabled = $dbByRegistryId[$m->registryId];
                }
                // Otherwise, it's a new addon, so it's disabled by default
                else {
                    $this->upsert($m);
                    $enabled = false;
                }
            }

            // The addon does not have a registry_id - so it's legacy
            else {
                // If the addon is already in the DB (search by the path), use its enabled flag.
                if (array_key_exists($m->path, $dbByPath)) {
                    $enabled = $dbByPath[$m->path];
                } else {
                    $this->upsert($m);
                    $enabled = false;
                }
            }

            $rows[] = $this->buildRow($m, $enabled);
        }

        $this->bootCache->write($rows);
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
     * Enumerate immediate subdirectories of $dir and parse each manifest.
     *
     * Returns an empty array when the directory does not exist; storage/app/addons
     * may be absent on a fresh install (LOAD-01).
     *
     * Path-traversal guard (T-04-03): resolved realpath must stay within the
     * scanned base dir; suspicious directories are skipped and logged.
     *
     * @return ManifestData[]
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
                Log::warning(sprintf("PrimeService: skipping '%s' — path traversal guard triggered (T-04-03)", $subDir));

                continue;
            }

            $manifest = $this->parser->parse($resolved);

            if (!$manifest instanceof ManifestData) {
                Log::warning(sprintf("PrimeService: skipping '%s' — module.json is missing or invalid (D-15)", $resolved));

                continue;
            }

            $results[] = $manifest;
        }

        return $results;
    }

    /**
     * Build an AddonCacheEntry from a manifest and an explicit enabled flag.
     */
    private function buildRow(ManifestData $m, bool $enabled): AddonRuntime
    {
        return new AddonRuntime(
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
    private function upsert(ManifestData $m): Addon
    {
        if ($m->registryId !== null) {
            $addon = Addon::query()->firstOrNew(['registry_id' => $m->registryId]);
        } else {
            $addon = Addon::query()->firstOrNew(['path' => $m->path]);
        }

        $isNew = !$addon->exists;

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
    private function probeFilament(ManifestData $m): array
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
