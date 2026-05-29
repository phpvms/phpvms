<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Consolidated migration: rename active->enabled, add bundle_id (nullable),
     * seed a bundle named "Default", backfill bundle_id, tighten bundle_id to
     * NOT NULL, and create all related indexes.
     *
     * Performed in a single migration so the index creation references the
     * post-rename column name (avoids MySQL ER_BAD_FIELD_ERROR on `enabled`).
     */
    public function up(): void
    {
        // 1. Rename active -> enabled first.
        Schema::table('flights', function (Blueprint $table): void {
            $table->renameColumn('active', 'enabled');
            $table->boolean('enabled')->default(true)->change();
        });

        // 2. Add bundle_id (nullable temporarily so we can backfill) and bundle FK.
        Schema::table('flights', function (Blueprint $table): void {
            $table->foreignId('bundle_id')->nullable()->after('user_id')->constrained('flight_bundles')->restrictOnDelete();
        });

        // 3. Ensure a "Default" bundle exists (idempotent by name).
        $defaultId = DB::table('flight_bundles')->where('name', 'Default')->value('id');
        if ($defaultId === null) {
            $defaultId = DB::table('flight_bundles')->insertGetId([
                'name'       => 'Default',
                'enabled'    => true,
                'visible'    => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Backfill bundle_id for any existing rows.
        DB::table('flights')->whereNull('bundle_id')->update(['bundle_id' => $defaultId]);

        // 5. Tighten bundle_id to NOT NULL now that every row has a value.
        Schema::table('flights', function (Blueprint $table): void {
            $table->foreignId('bundle_id')->nullable(false)->change();
        });

        // 6. Create indexes against the post-rename `enabled` column.
        Schema::table('flights', function (Blueprint $table): void {
            $table->index(['bundle_id', 'enabled', 'visible'], 'flights_bundle_id_enabled_visible_index');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropIndex('flights_bundle_id_enabled_visible_index');
            $table->dropConstrainedForeignId('bundle_id');
            $table->renameColumn('enabled', 'active');
        });
    }
};
