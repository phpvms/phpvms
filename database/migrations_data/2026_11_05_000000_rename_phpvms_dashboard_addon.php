<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: carry the bundled `phpvms/dashboard` addon row over to its new
 * identity, `phpvms/widgets` (modules/phpvms-dashboard → modules/phpvms-widgets).
 *
 * registry_id is the addon identity, so without this an install that enabled the
 * old module would keep an enabled row pointing at a deleted directory, and
 * discovery would add the renamed module as a new, disabled row.
 *
 * The old row is re-pointed rather than replaced, so its id, `enabled` and
 * `installed_at` survive. A `phpvms/widgets` row that discovery may already have
 * inserted is merged in (enabled if either was) and deleted first, because
 * `namespace` is unique. Settings keyed on the old registry_id move with it.
 */
return new class() extends Migration
{
    private const string OLD_REGISTRY_ID = 'phpvms/dashboard';

    private const string OLD_NAMESPACE = 'Modules\\PhpvmsDashboard';

    private const string NEW_REGISTRY_ID = 'phpvms/widgets';

    private const string NEW_NAMESPACE = 'Modules\\PhpvmsWidgets';

    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        $old = DB::table('addons')
            ->where('registry_id', self::OLD_REGISTRY_ID)
            ->orWhere('namespace', self::OLD_NAMESPACE)
            ->first();

        if ($old === null) {
            return;
        }

        $new = DB::table('addons')
            ->where('registry_id', self::NEW_REGISTRY_ID)
            ->orWhere('namespace', self::NEW_NAMESPACE)
            ->first();

        DB::transaction(function () use ($old, $new): void {
            if ($new !== null) {
                DB::table('addons')->where('id', $new->id)->delete();
            }

            DB::table('addons')->where('id', $old->id)->update([
                'name'        => 'PhpvmsWidgets',
                'registry_id' => self::NEW_REGISTRY_ID,
                'namespace'   => self::NEW_NAMESPACE,
                'path'        => base_path('modules/phpvms-widgets'),
                'enabled'     => (bool) $old->enabled || (bool) ($new->enabled ?? false),
                'updated_at'  => now(),
            ]);

            if (Schema::hasTable('addon_settings_backup') && Schema::hasColumn('addon_settings_backup', 'registry_id')) {
                DB::table('addon_settings_backup')
                    ->where('registry_id', self::OLD_REGISTRY_ID)
                    ->update(['registry_id' => self::NEW_REGISTRY_ID]);
            }
        });
    }
};
