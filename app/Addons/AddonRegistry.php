<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Models\AddonManifest;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\AddonSource;
use App\Addons\Support\AddonAssetLinker;
use App\Addons\Support\AddonRoots;
use App\Addons\Support\AddonValidator;
use App\Addons\Support\ManifestParser;
use App\Addons\Support\OctaneReloader;
use App\Events\AddonInstalled;
use App\Events\AddonUpdated;
use App\Exceptions\AddonInstallException;
use App\Exceptions\AddonNotFoundException;
use App\Models\Addon;
use App\Models\AddonSetting;
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
     * Uninstall an addon, and report whether it was actually removed.
     *
     * What this does depends on which root the addon lives in. In the writable
     * installed root it is a real uninstall: row, asset links and files all go,
     * and it returns true. In the image root nothing can be removed — the files
     * belong to the image layer — so it is disabled instead, keeps its row, and
     * returns false. Callers must use the return value to describe the outcome;
     * telling an operator an addon was deleted when it was only switched off is
     * the misreport this return exists to prevent. Unknown addon: false.
     *
     * When $removeTables is true, the addon's schema migrations are rolled back
     * (dropping its tables), its seed markers are cleared, and its saved
     * settings are deleted, so a later reinstall starts clean and re-seeds.
     *
     * When it is false the settings rows are deliberately left behind: they are
     * keyed on registry_id, not the addon row id, so reinstalling the addon
     * finds the operator's values again instead of resetting them to defaults.
     *
     * @return AddonDeleteOutcome which of the three things happened, so the
     *                            caller can report it without guessing
     */
    public function delete(string $name, bool $removeTables = false): AddonDeleteOutcome
    {
        $addon = $this->find($name);

        if (!$addon instanceof Addon) {
            return AddonDeleteOutcome::NotFound;
        }

        if ($addon->isBundled()) {
            throw new RuntimeException(sprintf('The "%s" addon is bundled with phpVMS and cannot be deleted.', $name));
        }

        // An addon shipped in the image cannot be removed from disk at runtime:
        // the files belong to the image layer, and the next deploy restores
        // them regardless. Deleting the row alone would be worse than useless —
        // discoverNewAddons() re-adds any manifest without one, so the addon
        // would silently come back. Disable it instead and keep the row, so it
        // stays listed and stays switchable.
        if (AddonRoots::isImageManaged($addon->getPath())) {
            // $removeTables is deliberately ignored here. The files stay, so the
            // addon can be re-enabled at any time; dropping its tables would
            // leave it re-enableable with no schema. The false return is how the
            // caller knows to say "disabled" rather than "removed" — reporting a
            // removal that did not happen is worse than refusing it.
            $this->setEnabled($name, false);

            return AddonDeleteOutcome::Disabled;
        }

        // The loaded copy is a registry update sitting on top of a version the
        // image still ships. Removing it is a revert, not an uninstall: the
        // image's copy takes over on the rebuild below, so the row stays (now
        // pointing at the image path) and the addon's tables and settings must
        // survive — the addon is still installed, just at the bundled version.
        $imageCopy = $this->imageRootCopy($addon->registry_id);

        if ($imageCopy instanceof AddonManifest) {
            // Before any row state changes: a surviving overlay is still the
            // newer copy, so it would keep winning precedence and the "revert"
            // would be a lie.
            $this->removeAddonFilesOrFail($addon);

            // Re-point the row at the image copy explicitly. Neither
            // discoverNewAddons() nor rebuildCache() will do it: the first skips
            // addons that already have a row, and the second only regenerates
            // the boot cache. Left alone, the row would still name the directory
            // just deleted and the addon would stop loading entirely.
            $addon->path = $imageCopy->path;
            $addon->version = $imageCopy->version;
            $addon->namespace = $imageCopy->namespace;
            $addon->save();

            $this->assetLinker->link($addon->getName(), $addon->getPath());

            app(AddonDiscoveryService::class)->rebuildCache();
            $this->bustPanelComponentCache();
            $this->octane->reload();

            return AddonDeleteOutcome::Reverted;
        }

        // Files first, and it throws on failure. Dropping the tables before
        // knowing the files are gone would leave a discoverable addon whose
        // schema has been deleted under it.
        $this->removeAddonFilesOrFail($addon);

        if ($removeTables) {
            $this->removeAddonTables($addon);
            app(SeederService::class)->clearAddonSeedMarkers($addon);
            AddonSetting::query()->where('registry_id', $addon->registry_id)->delete();
        }

        $this->assetLinker->unlink($addon->getName());

        $addon->delete();

        app(AddonDiscoveryService::class)->rebuildCache();
        $this->bustPanelComponentCache();
        $this->octane->reload();

        return AddonDeleteOutcome::Removed;
    }

    /**
     * The deployment image's copy of this addon, if it ships one.
     *
     * Read off disk rather than from the addons table: the table holds one row
     * per addon, pointing at whichever copy currently wins precedence, so it
     * cannot say whether a second copy exists in the other root.
     */
    private function imageRootCopy(?string $registryId): ?AddonManifest
    {
        if ($registryId === null || $registryId === '' || !File::isDirectory(AddonRoots::image())) {
            return null;
        }

        $parser = app(ManifestParser::class);

        foreach (File::directories(AddonRoots::image()) as $directory) {
            // Parse the resolved path, exactly as
            // AddonDiscoveryService::scanLocation() does, so the manifest path
            // written back to the row matches what discovery would store. A
            // mix of resolved and unresolved paths in that column is what made
            // symlinked modules misclassify in the first place.
            $resolved = realpath($directory);

            if ($resolved === false) {
                continue;
            }

            $manifest = $parser->parse($resolved);

            if ($manifest?->registryId === $registryId) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Delete an addon's files from the writable installed root, or throw.
     *
     * Without this, uninstalling is not durable: delete() removes the row, and
     * the next `discoverNewAddons()` pass finds the untouched manifest and
     * re-adds it, with `scan_for_new_on_boot` on by default. The addon
     * reappears after a restart.
     *
     * Verified by re-checking the directory rather than trusting the return
     * value. `Filesystem::deleteDirectory()` discards the result of its own
     * recursive calls and uses `@rmdir()`, then returns true unconditionally,
     * so it reports success even when files survive — a read-only file or a
     * permissions problem would otherwise leave the addon on disk while its row
     * disappeared.
     *
     * Containment guard: only paths inside the installed root are touched. The
     * caller has already excluded image-managed addons, but `addons.path` is a
     * stored string — a hand-edited or legacy row must not be able to aim a
     * recursive delete at an arbitrary directory. A path outside the root is
     * logged and left alone, and is not an error: there is nothing of ours
     * there to remove.
     *
     * @throws RuntimeException when the directory is still present afterwards
     */
    private function removeAddonFilesOrFail(Addon $addon): void
    {
        $path = rtrim($addon->getPath(), '/');
        $root = AddonRoots::installed();

        // Resolved on both sides, for the same reason AddonRoots::isImageManaged()
        // resolves: discovery stores realpath()s, and the configured root may
        // itself contain a symlinked component. Resolving also closes the
        // direction that matters for a recursive delete — a symlink inside the
        // writable root pointing elsewhere resolves outside it and is refused.
        $path = realpath($path) ?: $path;
        $root = realpath($root) ?: $root;

        if ($path === '' || $root === '' || !str_starts_with($path, $root.'/')) {
            Log::warning(sprintf(
                'AddonRegistry: not removing files for "%s" — path %s is outside the installed addon root %s',
                $addon->getName(),
                $path === '' ? '(empty)' : $path,
                $root,
            ));

            return;
        }

        if (!File::isDirectory($path)) {
            return;
        }

        File::deleteDirectory($path);

        // PHP caches stat results per request, so the check below has to be
        // told to look at the filesystem again rather than answer from the
        // cached entry that said the directory existed a moment ago.
        clearstatcache(true, $path);

        if (file_exists($path)) {
            throw new RuntimeException(sprintf(
                'Failed to remove the files for the "%s" addon at %s. Nothing was changed; the addon is still installed.',
                $addon->getName(),
                $path,
            ));
        }
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
        $staging = $this->freshStagingPath();
        $extracted = $this->fetchIntoStaging($source, $staging);

        try {
            $manifest = $this->validator->validate($extracted);
            $safeName = $this->safeName($manifest);
            $dest = AddonRoots::installed().'/'.$safeName;

            // Identity, not directory name. A second copy in the writable root
            // would be ignored by discovery in favour of the image one, leaving
            // files nothing ever loads — but the image root's directories are
            // named arbitrarily (modules/Awards, modules/phpvms-addon-manager),
            // so comparing $safeName against them would miss the duplicate.
            // registry_id is the identity discovery itself de-duplicates on.
            if (Addon::query()->where('registry_id', $manifest->registryId)->exists()) {
                throw new AddonInstallException(sprintf('Addon already installed: %s', $manifest->name));
            }

            // Belt and braces for a directory with no row behind it, e.g. a
            // previous install that failed after placing files.
            if (File::exists($dest)) {
                throw new AddonInstallException(sprintf('Addon already installed: %s', $manifest->name));
            }

            File::ensureDirectoryExists(AddonRoots::installed());

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

        $staging = $this->freshStagingPath();
        $extracted = $this->fetchIntoStaging($source, $staging);
        $backup = null;

        try {
            $manifest = $this->validator->validate($extracted);
            $dest = AddonRoots::installed().'/'.$this->safeName($manifest);

            File::ensureDirectoryExists(AddonRoots::installed());

            // Updating a copy that lives in the image root places the new
            // version in the writable root for the first time, so there is
            // nothing at $dest to move aside and $backup stays null. Record
            // that, or restorePreviousVersion() leaves the failed new version
            // sitting there — and a newer-looking broken copy would then win
            // precedence over the working image one.
            $destWasCreated = !File::isDirectory($dest);

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
            $this->restorePreviousVersion($existing, $prior, $dest ?? null, $backup, $destWasCreated ?? false);
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
    private function restorePreviousVersion(Addon $addon, array $prior, ?string $dest, ?string $backup, bool $destWasCreated = false): void
    {
        // Nothing was moved aside because nothing was there: the addon is still
        // whole in the image root, and all that has to go is the half-placed
        // new copy. Leaving it would shadow the working one on version.
        if ($destWasCreated && $backup === null && $dest !== null) {
            File::deleteDirectory($dest);
        }

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
     * Staging root for install/update extraction.
     *
     * Kept outside the scanned addons base so half-extracted archives are never
     * picked up by the discovery scanner.
     */
    private function stagingPath(): string
    {
        return (string) config('addons.paths.staging', storage_path('app/addon-staging'));
    }

    /**
     * A staging directory the calling operation owns outright.
     *
     * install() and update() both delete their staging directory when they are
     * done, so they must not share one. Two concurrent operations against the
     * staging root — in production, or two Pest processes under --parallel —
     * would otherwise have the first to finish delete the other's extracted
     * files, and the rename that places the addon fails with "Failed to place
     * addon" for reasons that have nothing to do with the addon.
     */
    private function freshStagingPath(): string
    {
        return $this->stagingPath().'/'.uniqid('op_', true);
    }

    /**
     * Fetch a source into a staging directory the caller owns, taking the
     * directory with it if the fetch fails.
     *
     * The directory has to exist before the source runs, and the callers' own
     * cleanup cannot start until they hold an extracted path — so a fetch that
     * throws (a 404, an empty or oversized download, an archive that is not a
     * zip) lands in the gap between the two. One shared staging directory hid
     * that: the next attempt reused it. One directory per attempt does not, so
     * the failure path has to clean up after itself or every failure orphans
     * another directory that nothing ever sweeps.
     *
     * @throws Throwable whatever the source raised, unchanged
     */
    private function fetchIntoStaging(AddonSource $source, string $staging): string
    {
        File::ensureDirectoryExists($staging);

        try {
            return $source->fetch($staging);
        } catch (Throwable $throwable) {
            File::deleteDirectory($staging);

            throw $throwable;
        }
    }

    /**
     * Derive a filesystem-safe directory name from an addon manifest.
     *
     * Converts the registry_id to a lowercase slug via keyed_str(): slashes
     * become hyphens, non-alphanumeric chars are stripped. A registry_id of
     * "phpvms/vmsacars" produces "phpvms-vmsacars".
     *
     * Falls back to keyed_str() on the manifest name for the edge case of a
     * registry_id that sanitises to nothing, keeping the same guarantee.
     *
     * @throws AddonInstallException when no safe characters remain after sanitisation
     */
    private function safeName(AddonManifest $manifest): string
    {
        // registryId is a non-nullable string, so the old `!== null` guard here
        // could never fail; the empty check below is the one that matters, and
        // an id that sanitises away still falls through to the name.
        $safe = keyed_str(strtolower($manifest->registryId));

        if ($safe !== '') {
            return $safe;
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
