<?php

declare(strict_types=1);

namespace App\Addons;

use App\Contracts\Service;
use App\Models\Addon;
use Illuminate\Support\Collection;

/**
 * Read API over the boot cache and the addons table.
 *
 * Stateless and Octane-safe: reads fresh on every call. No mutable instance
 * state — another Octane worker may have re-primed the cache between requests.
 */
class AddonRegistry extends Service
{
    public function __construct(
        private readonly BootCache $bootCache,
    ) {}

    /**
     * Return enabled addons from the boot cache (DB-free hot path, D-10).
     *
     * Returns an empty array when the cache is absent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enabled(): array
    {
        return $this->bootCache->read();
    }

    /**
     * Return an Eloquent Collection of every Addon row (enabled and disabled).
     *
     * @return Collection<int, Addon>
     */
    public function all(): Collection
    {
        return Addon::query()->get();
    }

    /**
     * Find an Addon by registry_id or path.
     *
     * Returns null when no matching row is found.
     */
    public function find(string $registryIdOrPath): ?Addon
    {
        return Addon::query()
            ->where('registry_id', $registryIdOrPath)
            ->orWhere('path', $registryIdOrPath)
            ->first();
    }
}
