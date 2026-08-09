<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Data migration: populate the addons.registry_id column from each addon's
     * module.json.
     *
     * create_addons_table has already shipped and deliberately stays untouched —
     * it seeds every row with registry_id = null, so this migration is the only
     * thing that ever populates the column. Fresh installs are covered too: the
     * installer runs `migrate` and then `migrate-data` in one pass, so a new
     * database gets the value right after the table is seeded.
     *
     * Without it, AddonSettingSyncService::resolveAddonId() looks an addon up by
     * the registry_id its manifest declares, misses the null column, and silently
     * syncs none of that addon's declared settings.
     *
     * Derives the value from each addon's module.json `registry_id`. Idempotent:
     * only rows whose registry_id is null or blank are touched, so operator-set
     * values are preserved. Rows whose manifest is missing, invalid, or declares
     * no registry_id are left null — they continue to resolve by namespace.
     *
     * Guarded on the registry_id column existing so it is a safe no-op on a
     * schema that has not yet gained the column.
     */
    public function up(): void
    {
        if (!Schema::hasTable('addons') || !Schema::hasColumn('addons', 'registry_id')) {
            return;
        }

        DB::table('addons')
            ->where(function ($query): void {
                $query->whereNull('registry_id')->orWhere('registry_id', '');
            })
            ->get(['id', 'path'])
            ->each(function (object $addon): void {
                $registryId = $this->resolveRegistryId((string) $addon->path);

                if ($registryId === null) {
                    return;
                }

                DB::table('addons')
                    ->where('id', $addon->id)
                    ->update(['registry_id' => $registryId]);
            });
    }

    /**
     * Resolve the declared registry_id from module.json, or null when the
     * file is missing, invalid, or declares nothing.
     */
    private function resolveRegistryId(string $path): ?string
    {
        $manifestPath = $path.'/module.json';

        if (!File::exists($manifestPath)) {
            return null;
        }

        $data = json_decode(File::get($manifestPath), true);

        if (!is_array($data) || !isset($data['registry_id'])) {
            return null;
        }

        // A non-scalar registry_id (a third-party manifest declaring an array or
        // object) would raise "Array to string conversion" on the cast below and
        // abort migrate-data, blocking the whole install. Skip it instead.
        if (!is_scalar($data['registry_id'])) {
            return null;
        }

        // D-03: blank/whitespace-only registry_id normalises to null, matching
        // ManifestParser — otherwise the row and the boot cache disagree.
        $trimmed = trim((string) $data['registry_id']);

        return $trimmed !== '' ? $trimmed : null;
    }
};
