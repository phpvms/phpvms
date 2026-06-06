<?php

declare(strict_types=1);

namespace App\Addons;

use App\Addons\Models\AddonRuntime;
use App\Models\Addon;
use Illuminate\Support\Collection;

/**
 * Read API over the boot cache and the addons table.
 *
 * Stateless and Octane-safe: reads fresh on every call. No mutable instance
 * state — another Octane worker may have re-primed the cache between requests.
 */
class AddonRegistry
{
    public function __construct(
        private readonly BootCache $bootCache,
    ) {}

    /**
     * Return enabled addons from the boot cache (DB-free hot path, D-10).
     *
     * Returns an empty array when the cache is absent.
     *
     * @return Collection<AddonRuntime>
     */
    public function enabled(): Collection
    {
        return collect(array_values(
            array_filter(
                $this->bootCache->read(),
                fn (AddonRuntime $entry) => $entry->enabled,
            )
        ));
    }

    /**
     * Return an Eloquent Collection of every Addon row (enabled and disabled).
     *
     * @return Collection<AddonRuntime>
     */
    public function all(): Collection
    {
        return collect(array_map(fn (AddonRuntime $record) => $record, $this->bootCache->read()));
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
