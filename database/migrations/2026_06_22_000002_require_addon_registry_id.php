<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Make `addons.registry_id` the addon's identity: non-null and unique.
     *
     * Earlier builds of create_addons_table seeded every bundled row with a null
     * registry_id, and AddonDiscoveryService::discoverNewAddons() skips rows it
     * already matches by name/namespace — so a null was never healed. Anything
     * keyed on the column (settings sync, the registry client) silently fell
     * back or no-opped.
     *
     * The reconcile has to happen here rather than in a data migration: the
     * `migrate-data` pass runs after every schema migration, so the NOT NULL
     * change below would hit the nulls first.
     *
     * Rows whose manifest is gone or declares no registry_id are deleted — with
     * the column required there is no identity to give them, and the next boot's
     * discovery re-inserts anything that is still on disk and valid.
     */
    public function up(): void
    {
        if (!Schema::hasTable('addons') || !Schema::hasColumn('addons', 'registry_id')) {
            return;
        }

        $this->reconcileFromDisk();
        $this->deleteUnidentifiable();
        $this->deleteDuplicates();

        Schema::table('addons', function (Blueprint $table): void {
            $table->string('registry_id')->nullable(false)->change();
            $table->unique('registry_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        Schema::table('addons', function (Blueprint $table): void {
            $table->dropUnique(['registry_id']);
            $table->string('registry_id')->nullable()->change();
        });
    }

    /**
     * Fill blank registry_ids from each row's module.json.
     */
    private function reconcileFromDisk(): void
    {
        DB::table('addons')
            ->whereNull('registry_id')
            ->orWhere('registry_id', '')
            ->get(['id', 'path'])
            ->each(function (object $addon): void {
                $registryId = $this->resolveRegistryId((string) $addon->path);

                if ($registryId === null) {
                    return;
                }

                DB::table('addons')->where('id', $addon->id)->update(['registry_id' => $registryId]);
            });
    }

    /**
     * Drop rows that still have no identity. Their addon_settings go with them
     * via the existing cascade.
     */
    private function deleteUnidentifiable(): void
    {
        DB::table('addons')
            ->whereNull('registry_id')
            ->orWhere('registry_id', '')
            ->delete();
    }

    /**
     * Keep the oldest row per registry_id so the unique index can be added. A
     * duplicate can exist where one row was seeded by create_addons_table and
     * another inserted by discovery before the ids agreed.
     */
    private function deleteDuplicates(): void
    {
        DB::table('addons')
            ->select('registry_id')
            ->groupBy('registry_id')
            ->havingRaw('count(*) > 1')
            ->pluck('registry_id')
            ->each(function ($registryId): void {
                $keep = DB::table('addons')->where('registry_id', $registryId)->min('id');

                DB::table('addons')
                    ->where('registry_id', $registryId)
                    ->where('id', '!=', $keep)
                    ->delete();
            });
    }

    /**
     * The registry_id declared by the module.json at $path, or null when the
     * manifest is missing, invalid, or declares none.
     */
    private function resolveRegistryId(string $path): ?string
    {
        $manifestPath = $path.'/module.json';

        if (!File::exists($manifestPath)) {
            return null;
        }

        $data = json_decode(File::get($manifestPath), true);

        if (!is_array($data) || !isset($data['registry_id']) || !is_scalar($data['registry_id'])) {
            return null;
        }

        $trimmed = trim((string) $data['registry_id']);

        return $trimmed !== '' ? $trimmed : null;
    }
};
