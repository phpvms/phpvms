<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\BootCache;
use App\Exceptions\AddonNotFoundException;
use App\Models\Addon;
use Illuminate\Support\Collection;

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
        private readonly BootCache $bootCache,
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
     * Whether the addon's code is actually loaded this worker — i.e. present in
     * the enabled set of the boot cache, which is what the autoloader reads.
     */
    public function isLoaded(string $name): bool
    {
        return $this->bootCache->enabled()
            ->contains(fn ($entry): bool => ($entry->name ?? basename($entry->path)) === $name);
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

        $addon->delete();

        app(AddonDiscoveryService::class)->rebuildCache();
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
    }
}
