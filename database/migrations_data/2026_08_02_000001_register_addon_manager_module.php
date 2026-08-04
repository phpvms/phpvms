<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: register the bundled Addon Manager module in the `addons`
 * table on existing installs, enabled and flagged bundled.
 *
 * Fresh installs are covered by create_addons_table, which seeds every module
 * directory when the table is empty. Existing installs already have a populated
 * table, so that seed is skipped — this migration ensures the row exists.
 *
 * It is safe to force `enabled`/`bundled` here: the module is brand-new at this
 * migration, so there is no prior operator choice to preserve, and on-disk
 * discovery may already have inserted it as a *disabled* row (discovery defaults
 * new addons to disabled) before this ran — this corrects that.
 */
return new class() extends Migration
{
    private const string NAMESPACE = 'Modules\\AddonManager';

    private const string DIRECTORY = 'modules/phpvms-addon-manager';

    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        $path = base_path(self::DIRECTORY);

        // The module ships in-repo; if the directory is absent (e.g. a trimmed
        // deployment) there is nothing to register.
        if (!File::isDirectory($path)) {
            return;
        }

        $now = now();

        // Upsert on the module's unique namespace: insert when absent, or force
        // the bundled row enabled when a (disabled) discovery row already exists.
        DB::table('addons')->updateOrInsert(
            ['namespace' => self::NAMESPACE],
            [
                'name'         => 'Addon Manager',
                'registry_id'  => 'phpvms/addon-manager',
                'type'         => 'module',
                'path'         => $path,
                'enabled'      => true,
                'bundled'      => true,
                'installed_at' => $now,
                'updated_at'   => $now,
            ],
        );
    }
};
