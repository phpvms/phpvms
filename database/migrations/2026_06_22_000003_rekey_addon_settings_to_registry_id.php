<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Key addon_settings on the addon's registry_id instead of its row id.
     *
     * The `addon_id` foreign key cascaded on delete, so uninstalling an addon
     * always destroyed its saved values — even though the delete modal only
     * offers to remove the addon's *tables* — and a reinstall came back with a
     * new row id that could never find them again. registry_id is stable across
     * delete/reinstall, so values survive unless the operator asks otherwise
     * (AddonRegistry::delete() now removes them explicitly).
     *
     * Dated next to create_addon_settings_table rather than "today" on purpose:
     * addon migrations that read settings at migrate time (vmsACARS does) run
     * in filename order, and they would hit the old column shape through the
     * current AddonSettingService if this landed after them.
     */
    public function up(): void
    {
        if (!Schema::hasTable('addon_settings') || Schema::hasColumn('addon_settings', 'registry_id')) {
            return;
        }

        Schema::table('addon_settings', function (Blueprint $table): void {
            $table->string('registry_id')->nullable()->after('id');
        });

        DB::table('addons')->get(['id', 'registry_id'])->each(function (object $addon): void {
            DB::table('addon_settings')
                ->where('addon_id', $addon->id)
                ->update(['registry_id' => $addon->registry_id]);
        });

        // Nothing to key on: the owning addon row is gone (or predates the
        // registry_id requirement), so the value can never be read again.
        DB::table('addon_settings')->whereNull('registry_id')->delete();

        Schema::table('addon_settings', function (Blueprint $table): void {
            // The foreign key goes first: MySQL backs the constraint with the
            // unique index and refuses to drop an index a constraint needs.
            $table->dropForeign(['addon_id']);
            $table->dropUnique(['addon_id', 'key']);
            $table->dropColumn('addon_id');
        });

        Schema::table('addon_settings', function (Blueprint $table): void {
            $table->string('registry_id')->nullable(false)->change();
            $table->unique(['registry_id', 'key']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('addon_settings') || !Schema::hasColumn('addon_settings', 'registry_id')) {
            return;
        }

        Schema::table('addon_settings', function (Blueprint $table): void {
            $table->dropUnique(['registry_id', 'key']);
            $table->foreignId('addon_id')->nullable()->after('id')->constrained('addons')->cascadeOnDelete();
        });

        DB::table('addons')->get(['id', 'registry_id'])->each(function (object $addon): void {
            DB::table('addon_settings')
                ->where('registry_id', $addon->registry_id)
                ->update(['addon_id' => $addon->id]);
        });

        DB::table('addon_settings')->whereNull('addon_id')->delete();

        Schema::table('addon_settings', function (Blueprint $table): void {
            $table->dropColumn('registry_id');
            $table->unique(['addon_id', 'key']);
        });
    }
};
