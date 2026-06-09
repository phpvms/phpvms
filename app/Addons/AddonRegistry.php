<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Models\AddonManifest;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\AddonSource;
use App\Addons\Support\AddonAssetLinker;
use App\Addons\Support\AddonValidator;
use App\Addons\Support\OctaneReloader;
use App\Exceptions\AddonInstallException;
use App\Exceptions\AddonNotFoundException;
use App\Models\Addon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

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
     */
    public function delete(string $name): void
    {
        $addon = $this->find($name);

        if (!$addon instanceof Addon) {
            return;
        }

        $this->assetLinker->unlink($addon->getName());

        $addon->delete();

        app(AddonDiscoveryService::class)->rebuildCache();
        $this->octane->reload();
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
        $staging = config('addons.paths.base').'/_staging';
        File::ensureDirectoryExists($staging);

        $extracted = $source->fetch($staging);

        try {
            $manifest = $this->validator->validate($extracted);
            $dest = config('addons.paths.base').'/'.$manifest->name;

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
        $this->octane->reload();

        return $addon;
    }

    /**
     * Update an installed addon's files from a new source, preserving its enabled
     * flag. Does NOT run migrations.
     *
     * @throws AddonInstallException
     */
    public function update(string $name, AddonSource $source): Addon
    {
        $existing = $this->find($name);

        if (!$existing instanceof Addon) {
            throw new AddonInstallException(sprintf('Addon not installed: %s', $name));
        }

        $wasEnabled = $existing->isEnabled();

        $staging = config('addons.paths.base').'/_staging';
        File::ensureDirectoryExists($staging);

        $extracted = $source->fetch($staging);

        try {
            $manifest = $this->validator->validate($extracted);
            $dest = config('addons.paths.base').'/'.$manifest->name;

            File::deleteDirectory($dest);

            if (!File::moveDirectory($extracted, $dest)) {
                throw new AddonInstallException(sprintf('Failed to place addon: %s', $manifest->name));
            }
        } finally {
            File::deleteDirectory($staging);
        }

        $existing->version = $manifest->version;
        $existing->namespace = $manifest->namespace;
        $existing->path = $dest;
        $existing->enabled = $wasEnabled;
        $existing->save();

        app(AddonDiscoveryService::class)->rebuildCache();

        $this->assetLinker->link($existing->getName(), $existing->getPath());
        $this->octane->reload();

        return $existing->refresh();
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

        $addon->enabled = $enabled;
        $addon->save();

        app(AddonDiscoveryService::class)->rebuildCache();
        $this->octane->reload();
    }
}
