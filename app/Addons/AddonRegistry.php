<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Models\AddonManifest;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\AddonSource;
use App\Addons\Support\AddonAssetLinker;
use App\Addons\Support\AddonValidator;
use App\Addons\Support\ManifestParser;
use App\Addons\Support\OctaneReloader;
use App\Events\AddonInstalled;
use App\Events\AddonUpdated;
use App\Exceptions\AddonInstallException;
use App\Exceptions\AddonNotFoundException;
use App\Models\Addon;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Lifecycle façade for addons. Owns reads (find/all/enabled), enable/disable,
 * delete, install, update, asset linking, and Octane refresh.
 *
 * Reads return Addon Eloquent models. "enabled" is DB intent; "loaded" is the
 * boot-cache reality (what actually got PSR-4/provider-registered this worker).
 */
class AddonRegistry
{
    public function __construct(
        private readonly AddonAssetLinker $assetLinker,
        private readonly OctaneReloader $octane,
        private readonly AddonValidator $validator,
    ) {}

    /**
     * Find an addon by display name; null when not found.
     */
    public function find(string $name): ?Addon
    {
        return $this->all()->first(fn (Addon $addon): bool => $addon->getName() === $name);
    }

    /**
     * Find an addon by display name or throw.
     *
     * @throws AddonNotFoundException
     */
    public function findOrFail(string $name): Addon
    {
        return $this->find($name) ?? throw new AddonNotFoundException($name);
    }

    /**
     * Every addon row (enabled and disabled).
     *
     * @return Collection<int, Addon>
     */
    public function all(): Collection
    {
        return Addon::query()->get();
    }

    /**
     * Enabled addons (DB intent).
     *
     * @return Collection<int, Addon>
     */
    public function enabled(): Collection
    {
        return Addon::query()->where('enabled', true)->get();
    }

    /**
     * Enable an addon: flip the DB flag and regenerate the boot cache.
     * No-op when the addon is unknown.
     */
    public function enable(string $name): void
    {
        $this->setEnabled($name, true);
    }

    /**
     * Disable an addon: flip the DB flag and regenerate the boot cache.
     * No-op when the addon is unknown.
     */
    public function disable(string $name): void
    {
        $this->setEnabled($name, false);
    }

    /**
     * Delete an addon's DB row and regenerate the boot cache.
     * Does NOT remove files on disk. No-op when the addon is unknown.
     *
     * When $removeTables is true, the addon's schema migrations are rolled back
     * (dropping its tables) and its seed markers are cleared before the row is
     * removed, so a later reinstall starts from a clean schema and re-seeds.
     */
    public function delete(string $name, bool $removeTables = false): void
    {
        $addon = $this->find($name);

        if (!$addon instanceof Addon) {
            return;
        }

        if ($addon->isBundled()) {
            throw new RuntimeException(sprintf('The "%s" addon is bundled with phpVMS and cannot be deleted.', $name));
        }

        if ($removeTables) {
            $this->removeAddonTables($addon);
            app(SeederService::class)->clearAddonSeedMarkers($addon);
        }

        $this->assetLinker->unlink($addon->getName());

        $addon->delete();

        app(AddonDiscoveryService::class)->rebuildCache();
        $this->bustPanelComponentCache();
        $this->octane->reload();
    }

    /**
     * Remove an addon's database tables on uninstall.
     *
     * Prefers the addon's declared `database.tables` contract from module.json:
     * those tables are dropped explicitly and the addon's migration records are
     * purged, so removal does not depend on the migrations having correct down()
     * methods. When no contract is declared, falls back to rolling back the
     * addon's migrations (running their down() methods).
     */
    private function removeAddonTables(Addon $addon): void
    {
        $migrationSvc = app(MigrationService::class);
        $tables = $this->declaredTables($addon);

        if ($tables !== []) {
            $migrationSvc->dropAddonTables($tables);
            $migrationSvc->purgeAddonMigrationRecords($addon);

            return;
        }

        $migrationSvc->rollbackAddonMigrations($addon);
    }

    /**
     * Resolve the tables an addon declares it owns via module.json.
     *
     * @return list<string>
     */
    private function declaredTables(Addon $addon): array
    {
        $manifest = app(ManifestParser::class)->parse($addon->getPath());

        if (!$manifest instanceof AddonManifest) {
            return [];
        }

        return $manifest->tables;
    }

    /**
     * Rebuild public asset symlinks for every enabled addon.
     */
    public function relinkAssets(): void
    {
        foreach ($this->enabled() as $addon) {
            $this->assetLinker->link($addon->getName(), $addon->getPath());
        }
    }

    /**
     * Install an addon from a source (zip/url): fetch → validate → place →
     * register → link assets → reload workers. Does NOT run migrations.
     *
     * @throws AddonInstallException
     */
    public function install(AddonSource $source): Addon
    {
        $staging = $this->stagingPath();
        File::ensureDirectoryExists($staging);

        $extracted = $source->fetch($staging);

        try {
            $manifest = $this->validator->validate($extracted);
            $dest = config('addons.paths.base').'/'.$this->safeName($manifest);

            if (File::exists($dest)) {
                throw new AddonInstallException(sprintf('Addon already installed: %s', $manifest->name));
            }

            if (!File::moveDirectory($extracted, $dest)) {
                throw new AddonInstallException(sprintf('Failed to place addon: %s', $manifest->name));
            }
        } finally {
            File::deleteDirectory($staging);
        }

        $addon = $this->register($manifest, $dest);

        $this->assetLinker->link($addon->getName(), $addon->getPath());
        $this->bustPanelComponentCache();
        $this->octane->reload();

        AddonInstalled::dispatch($addon);

        return $addon;
    }

    /**
     * Update an installed addon's files from a new source, preserving its enabled
     * flag. Does NOT run migrations itself, but accepts an optional post-placement
     * hook (e.g. running the new version's migrations) that runs inside the
     * protected window: if it throws, the previous version is restored.
     *
     * Non-destructive: the current version's directory is moved aside (not
     * deleted) before the new version is placed. On ANY failure — placement or
     * the post-placement hook — the previous directory and database row are
     * restored and no tables are dropped, so a failed update never uninstalls a
     * working addon.
     *
     * @param Closure(Addon):void|null $afterPlace runs after the row is saved
     *                                             and the boot cache rebuilt
     *
     * @throws AddonInstallException
     */
    public function update(string $name, AddonSource $source, ?Closure $afterPlace = null): Addon
    {
        $existing = $this->find($name);

        if (!$existing instanceof Addon) {
            throw new AddonInstallException(sprintf('Addon not installed: %s', $name));
        }

        // Snapshot the prior DB state so it can be restored on failure.
        $prior = [
            'version'   => $existing->version,
            'namespace' => $existing->namespace,
            'path'      => $existing->path,
            'enabled'   => $existing->isEnabled(),
        ];

        $staging = $this->stagingPath();
        File::ensureDirectoryExists($staging);

        $extracted = $source->fetch($staging);
        $backup = null;

        try {
            $manifest = $this->validator->validate($extracted);
            $dest = config('addons.paths.base').'/'.$this->safeName($manifest);

            // Move the current version aside instead of deleting it.
            if (File::isDirectory($dest)) {
                $backup = $dest.'.bak-'.uniqid('', true);

                if (!File::moveDirectory($dest, $backup)) {
                    throw new AddonInstallException(sprintf('Failed to stage the current version aside: %s', $manifest->name));
                }
            }

            if (!File::moveDirectory($extracted, $dest)) {
                throw new AddonInstallException(sprintf('Failed to place addon: %s', $manifest->name));
            }

            $existing->version = $manifest->version;
            $existing->namespace = $manifest->namespace;
            $existing->path = $dest;
            $existing->enabled = $prior['enabled'];
            $existing->save();

            app(AddonDiscoveryService::class)->rebuildCache();

            if ($afterPlace instanceof Closure) {
                $afterPlace($existing);
            }
        } catch (Throwable $throwable) {
            $this->restorePreviousVersion($existing, $prior, $dest ?? null, $backup);
            File::deleteDirectory($staging);

            throw $throwable instanceof AddonInstallException
                ? $throwable
                : new AddonInstallException(sprintf('Update failed: %s', $throwable->getMessage()), (int) $throwable->getCode(), $throwable);
        }

        File::deleteDirectory($staging);

        if ($backup !== null) {
            File::deleteDirectory($backup);
        }

        $this->assetLinker->link($existing->getName(), $existing->getPath());
        $this->bustPanelComponentCache();
        $this->octane->reload();

        AddonUpdated::dispatch($existing);

        return $existing->refresh();
    }

    /**
     * Restore an addon to its prior version after a failed update: put the
     * backed-up directory back, restore the DB row, rebuild the boot cache, and
     * relink assets. No tables are dropped — a failed update must not uninstall
     * a working addon.
     *
     * @param array{version: ?string, namespace: string, path: string, enabled: bool} $prior
     */
    private function restorePreviousVersion(Addon $addon, array $prior, ?string $dest, ?string $backup): void
    {
        if ($backup !== null && $dest !== null && File::isDirectory($backup)) {
            File::deleteDirectory($dest);

            if (!File::moveDirectory($backup, $dest)) {
                // Restore failed (disk full, cross-device, permissions): the DB
                // row below points at $dest which no longer exists. Surface it
                // loudly — the addon needs manual recovery from $backup.
                Log::error(sprintf(
                    'AddonRegistry: failed to restore "%s" from backup after a failed update; backup left at %s',
                    $addon->getName(),
                    $backup,
                ));
            }
        }

        $addon->version = $prior['version'];
        $addon->namespace = $prior['namespace'];
        $addon->path = $prior['path'];
        $addon->enabled = $prior['enabled'];
        $addon->save();

        app(AddonDiscoveryService::class)->rebuildCache();
        $this->assetLinker->link($addon->getName(), $addon->getPath());
        $this->bustPanelComponentCache();
        $this->octane->reload();
    }

    /**
     * Staging directory for install/update extraction.
     *
     * Kept outside the scanned addons base so half-extracted archives are never
     * picked up by the discovery scanner.
     */
    private function stagingPath(): string
    {
        return (string) config('addons.paths.staging', storage_path('app/addon-staging'));
    }

    /**
     * Derive a filesystem-safe directory name from an addon manifest.
     *
     * When a registry_id is present (managed addons), converts it to a
     * lowercase slug via keyed_str(): slashes become hyphens, non-alphanumeric
     * chars are stripped. A registry_id of "phpvms/vmsacars" produces "phpvms-vmsacars".
     *
     * Falls back to keyed_str() on the manifest name for unmanaged addons,
     * ensuring the same sanitisation guarantee in both paths.
     *
     * @throws AddonInstallException when no safe characters remain after sanitisation
     */
    private function safeName(AddonManifest $manifest): string
    {
        if ($manifest->registryId !== null) {
            $safe = keyed_str(strtolower($manifest->registryId));

            if ($safe !== '') {
                return $safe;
            }
        }

        $safe = keyed_str(strtolower($manifest->name));

        if ($safe === '') {
            throw new AddonInstallException(sprintf('Invalid addon name: %s', $manifest->name));
        }

        return $safe;
    }

    /**
     * Persist the addon row (enabled) and regenerate the boot cache.
     */
    private function register(AddonManifest $manifest, string $path): Addon
    {
        $addon = Addon::fromManifest($manifest);
        $addon->path = $path;
        $addon->enabled = true;
        $addon->installed_at = now();
        $addon->save();

        app(AddonDiscoveryService::class)->rebuildCache();

        return $addon;
    }

    /**
     * Persist the enabled flag and regenerate the boot cache.
     */
    private function setEnabled(string $name, bool $enabled): void
    {
        $addon = $this->find($name);

        if (!$addon instanceof Addon) {
            return;
        }

        if (!$enabled && $addon->isBundled()) {
            throw new RuntimeException(sprintf('The "%s" addon is bundled with phpVMS and cannot be disabled.', $name));
        }

        $addon->enabled = $enabled;
        $addon->save();

        app(AddonDiscoveryService::class)->rebuildCache();
        $this->bustPanelComponentCache();
        $this->octane->reload();
    }

    /**
     * Invalidate Filament's per-panel component cache after an addon state
     * change. Module Filament components are discovered into the admin panel at
     * registration (see AdminPanelProvider); once an operator has run
     * `filament:cache-components`, that cache is authoritative and Filament
     * skips rediscovery — so a newly enabled/disabled/installed/updated/deleted
     * module would otherwise keep serving its stale cached component list.
     * Clearing the cached panel files forces rediscovery on the next request;
     * operators re-run `filament:cache-components` to restore the optimization.
     *
     * Runs on every transition (not on passive boot-cache rebuilds) so a
     * freshly built component cache on a cold deploy is never wiped.
     */
    private function bustPanelComponentCache(): void
    {
        $dir = (config('filament.cache_path') ?? base_path('bootstrap/cache/filament')).'/panels';

        if (File::isDirectory($dir)) {
            File::cleanDirectory($dir);
        }
    }
}
