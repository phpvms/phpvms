<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Data migration: rekey `addon_seeded:%` kvp markers from the display name to
     * `registry_id ?? namespace`. SeederService::seedMarkerKey() used to build
     * the marker from the addon's display name, which is nullable, non-unique
     * and mutable — an addon rebrand moves the name, stranding the old marker and
     * leaving addonSeedsPending() permanently true for that addon.
     *
     * The key segment after `addon_seeded:` is split on the *last* colon so a
     * name containing one is not mis-parsed; the left part is the old name, the
     * right part is the version. The name is resolved against `addons.name` and
     * the key rewritten to the new identity. A name that matches no addon has its
     * marker deleted instead, so that addon's seeders simply re-run once on the
     * next update — addon seeders are expected to be idempotent.
     *
     * Idempotent: a key already in the new form carries an identity segment
     * (registry_id or namespace), not a display name, so it is skipped rather
     * than treated as unresolvable and deleted.
     *
     * Guarded on both tables existing so it is a safe no-op on a schema that
     * predates them.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kvp') || !Schema::hasTable('addons')) {
            return;
        }

        $addons = DB::table('addons')->get(['name', 'namespace', 'registry_id']);

        // Both identities go in the skip set, not just the one seedMarkerKey()
        // would pick today: a marker written on an addon's namespace before its
        // registry_id was populated is still new-format and must not be treated
        // as an unresolvable display name and deleted. Slugified the same way
        // SeederService::seedMarkerIdentity() does, since that is the form the
        // keys actually carry.
        $identities = $addons
            ->flatMap(fn (object $addon): array => array_map(
                fn (string $value): string => keyed_str(strtolower($value)),
                array_filter([$addon->registry_id, $addon->namespace]),
            ))
            ->flip();

        $byName = $addons->keyBy('name');

        DB::table('kvp')
            ->where('key', 'like', 'addon_seeded:%')
            ->get(['key'])
            ->each(function (object $kvp) use ($identities, $byName): void {
                $identitySegment = substr($kvp->key, strlen('addon_seeded:'));
                $lastColon = strrpos($identitySegment, ':');

                if ($lastColon === false) {
                    return;
                }

                $identity = substr($identitySegment, 0, $lastColon);
                $version = substr($identitySegment, $lastColon + 1);

                // Already keyed on registry_id/namespace — nothing to do.
                if ($identities->has($identity)) {
                    return;
                }

                $addon = $byName->get($identity);

                if ($addon === null) {
                    DB::table('kvp')->where('key', $kvp->key)->delete();

                    return;
                }

                // filled(), not ??, so an empty-string registry_id falls back to
                // namespace rather than producing `addon_seeded::{version}`, then
                // keyed_str() to match SeederService::seedMarkerIdentity() exactly.
                $identityValue = filled($addon->registry_id) ? $addon->registry_id : $addon->namespace;

                $newKey = 'addon_seeded:'.keyed_str(strtolower((string) $identityValue)).':'.$version;

                DB::table('kvp')->where('key', $kvp->key)->update(['key' => $newKey]);
            });
    }
};
