<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade the  schema (dashboard_grids + dashboard_grid_blocks + widgets-with-columns)
 * to v2 (template_key + sectioned widgets at {section_slug, x, y, w, h}) and
 * fold in the personal-dashboard columns so v1.x ships in a single migration.
 *
 * Steps:
 *  1. Add the new columns alongside the old ones so we can copy data across.
 *  2. Seed defaults on existing rows (template_key = 'flat-12', widgets land in
 *     the 'main' section with their old `columns` becoming `w` and their old
 *     `ordering` becoming `y`). GridStack will auto-compact on render.
 *  3. Drop the obsolete foreign keys, columns, indexes, and the grid tables.
 *  4. Add the new composite index.
 *  5. Add `is_personal` + `created_by` to `dashboards` (idempotent so this is
 *     a no-op on fresh installs where the create migration already shipped
 *     the columns).
 */
return new class() extends Migration
{
    public function up(): void
    {
        // 1. Add new columns (nullable / defaulted so the data step doesn't fail).
        if (Schema::hasTable('dashboards') && !Schema::hasColumn('dashboards', 'template_key')) {
            Schema::table('dashboards', function (Blueprint $table): void {
                $table->string('template_key')->nullable()->after('page');
            });
        }

        if (Schema::hasTable('dashboard_widgets')) {
            Schema::table('dashboard_widgets', function (Blueprint $table): void {
                if (!Schema::hasColumn('dashboard_widgets', 'section_slug')) {
                    $table->string('section_slug')->default('main')->after('type');
                }

                if (!Schema::hasColumn('dashboard_widgets', 'x')) {
                    $table->integer('x')->default(0)->after('section_slug');
                }

                if (!Schema::hasColumn('dashboard_widgets', 'y')) {
                    $table->integer('y')->default(0)->after('x');
                }

                if (!Schema::hasColumn('dashboard_widgets', 'w')) {
                    $table->integer('w')->default(4)->after('y');
                }

                if (!Schema::hasColumn('dashboard_widgets', 'h')) {
                    $table->integer('h')->default(1)->after('w');
                }
            });
        }

        // 2. Migrate data from v1 layout into v2 coordinates.
        DB::table('dashboards')
            ->whereNull('template_key')
            ->update(['template_key' => 'flat-12']);

        if (Schema::hasColumn('dashboard_widgets', 'ordering') && Schema::hasColumn('dashboard_widgets', 'columns')) {
            DB::table('dashboard_widgets')
                ->orderBy('dashboard_id')
                ->orderBy('ordering')
                ->select(['id', 'columns', 'ordering'])
                ->get()
                ->each(function (object $widget): void {
                    DB::table('dashboard_widgets')
                        ->where('id', $widget->id)
                        ->update([
                            'section_slug' => 'main',
                            'x'            => 0,
                            'y'            => (int) ($widget->ordering ?? 0),
                            'w'            => (int) ($widget->columns ?? 4),
                            'h'            => 1,
                        ]);
                });
        }

        // 3. Drop the obsolete bits from dashboard_widgets.
        if (Schema::hasTable('dashboard_widgets')) {
            Schema::table('dashboard_widgets', function (Blueprint $table): void {
                if (Schema::hasColumn('dashboard_widgets', 'dashboard_grid_block_id')) {
                    try {
                        $table->dropForeign(['dashboard_grid_block_id']);
                    } catch (Throwable) {
                        // FK may already be gone on partial re-runs.
                    }

                    $table->dropColumn('dashboard_grid_block_id');
                }

                if (Schema::hasColumn('dashboard_widgets', 'ordering')) {
                    try {
                        $table->dropIndex('idx_dashboard_widgets_ordering');
                    } catch (Throwable) {
                        // Index may not exist if a previous version renamed it.
                    }

                    $table->dropColumn('ordering');
                }

                if (Schema::hasColumn('dashboard_widgets', 'columns')) {
                    $table->dropColumn('columns');
                }
            });

            // 4. Composite index for the new (dashboard, section) lookups.
            try {
                Schema::table('dashboard_widgets', function (Blueprint $table): void {
                    $table->index(['dashboard_id', 'section_slug'], 'idx_dashboard_widgets_dashboard_section');
                });
            } catch (Throwable) {
                // Index already present on partial re-runs.
            }
        }

        // Drop the obsolete dashboard_grid_id link on dashboards.
        if (Schema::hasTable('dashboards') && Schema::hasColumn('dashboards', 'dashboard_grid_id')) {
            Schema::table('dashboards', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['dashboard_grid_id']);
                } catch (Throwable) {
                    // Continue if already dropped.
                }

                $table->dropColumn('dashboard_grid_id');
            });
        }

        // Finally, drop the grid tables themselves.
        Schema::dropIfExists('dashboard_grid_blocks');
        Schema::dropIfExists('dashboard_grids');

        // 5. Personal-dashboard columns. Idempotent — `Schema::hasColumn` guards
        //    mean fresh installs (where create_dynamic_dashboard_tables already
        //    shipped these columns) and partial re-runs both skip cleanly.
        if (Schema::hasTable('dashboards')) {
            $usersTable = (new (config('auth.providers.users.model', User::class)))->getTable();

            Schema::table('dashboards', function (Blueprint $table) use ($usersTable): void {
                if (!Schema::hasColumn('dashboards', 'is_personal')) {
                    $table->boolean('is_personal')->default(false)->after('is_locked');
                }

                if (!Schema::hasColumn('dashboards', 'created_by')) {
                    $table->foreignId('created_by')->nullable()
                        ->after('is_personal')
                        ->constrained($usersTable)
                        ->nullOnDelete();
                }
            });

            try {
                Schema::table('dashboards', function (Blueprint $table): void {
                    $table->index(['is_personal', 'created_by'], 'idx_dashboards_personal_creator');
                });
            } catch (Throwable) {
                // Index already present on partial re-runs / fresh install.
            }
        }
    }

    /**
     * v2 is a clean break — reversing it would mean reconstructing block data
     * that doesn't exist anymore. Refuse the down step rather than corrupt
     * the schema.
     */
    public function down(): never
    {
        throw new RuntimeException(
            'Migration upgrade_dynamic_dashboard_tables_to_v2 is not reversible. '
            .'Restore from a backup taken before the upgrade.'
        );
    }
};
