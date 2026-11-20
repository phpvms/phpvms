<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

use App\Features\Addons\AddonRegistry;
use App\Models\Addon;
use App\Models\Kvp;
use Carbon\Carbon;

/**
 * KVP markers recording that an addon version's seeders have run.
 */
final readonly class AddonSeedMarkers
{
    public function __construct(private AddonRegistry $registry) {}

    /**
     * Versioned, so an update re-runs the seeders. Keyed on registry_id, then
     * namespace: the display name is nullable, non-unique and mutable.
     */
    public function key(Addon $addon): string
    {
        return 'addon_seeded:'.$this->identity($addon).':'.($addon->version ?? 'base');
    }

    public function recorded(Addon $addon): bool
    {
        return Kvp::where('key', $this->key($addon))->exists();
    }

    public function record(Addon $addon): void
    {
        Kvp::updateOrCreate(
            ['key' => $this->key($addon)],
            ['value' => Carbon::now('UTC')->toDateTimeString()],
        );
    }

    /**
     * Remove every version's marker, so a reinstall re-runs the seeders.
     */
    public function clear(Addon $addon): void
    {
        // identity() leaves no LIKE metacharacters (no %, _ or backslash).
        Kvp::query()
            ->where('key', 'like', 'addon_seeded:'.$this->identity($addon).':%')
            ->delete();
    }

    /**
     * Whether any enabled addon ships a seeder whose marker is missing.
     */
    public function pending(): bool
    {
        foreach ($this->registry->enabled() as $addon) {
            if (AddonSeeders::for($addon->getPath()) !== [] && !$this->recorded($addon)) {
                return true;
            }
        }

        return false;
    }

    /**
     * filled() so an empty registry_id falls back to namespace; keyed_str()
     * keeps `-` so `Modules\Sample` and `ModulesSample` stay distinct.
     */
    private function identity(Addon $addon): string
    {
        $identity = filled($addon->registry_id) ? (string) $addon->registry_id : (string) $addon->namespace;

        return keyed_str(strtolower($identity));
    }
}
