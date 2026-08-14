<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashboard_grids', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('columns')->default(12);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('is_default', 'idx_dashboard_grids_is_default');
            $table->index('slug', 'idx_dashboard_grids_slug');
        });

        Schema::create('dashboard_grid_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dashboard_grid_id')->constrained('dashboard_grids')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('dashboard_grid_blocks')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('columns')->default(12);
            $table->boolean('display_empty')->default(false);
            $table->integer('ordering')->default(0);
            $table->timestamps();

            $table->unique(['dashboard_grid_id', 'slug'], 'uniq_grid_blocks_grid_slug');
            $table->index('dashboard_grid_id', 'idx_grid_blocks_grid_id');
            $table->index('parent_id', 'idx_grid_blocks_parent_id');
            $table->index('ordering', 'idx_grid_blocks_ordering');
        });

        $usersTable = (new (config('auth.providers.users.model', User::class)))->getTable();

        Schema::create('dashboards', function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->foreignId('dashboard_grid_id')->nullable()
                ->constrained('dashboard_grids')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('page')->nullable();
            $table->integer('ordering')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_personal')->default(false);
            // unsignedInteger, not foreignId: users.id is `increments()` (INT),
            // and MySQL rejects a BIGINT column referencing it.
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on($usersTable)->nullOnDelete();
            $table->json('settings')->nullable();
            $table->json('filters')->nullable();
            $table->json('display_filters')->nullable();
            $table->timestamps();

            $table->index('is_active', 'idx_dashboards_is_active');
            $table->index('ordering', 'idx_dashboards_ordering');
            $table->index(['is_personal', 'created_by'], 'idx_dashboards_personal_creator');
        });

        Schema::create('dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->foreignId('dashboard_grid_block_id')->nullable()
                ->constrained('dashboard_grid_blocks')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->integer('ordering')->default(0);
            $table->integer('columns')->default(3);
            $table->boolean('is_active')->default(true);
            $table->boolean('display_title')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('dashboard_id', 'idx_dashboard_widgets_dashboard_id');
            $table->index('is_active', 'idx_dashboard_widgets_is_active');
            $table->index('ordering', 'idx_dashboard_widgets_ordering');
            $table->index('type', 'idx_dashboard_widgets_type');
        });

        // Create default grid
        $gridId = DB::table('dashboard_grids')->insertGetId([
            'name'       => 'Default Template',
            'slug'       => 'default-template',
            'columns'    => 12,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create default block for the default grid
        DB::table('dashboard_grid_blocks')->insert([
            'dashboard_grid_id' => $gridId,
            'parent_id'         => null,
            'name'              => 'Main block',
            'slug'              => 'main-block',
            'columns'           => 12,
            'ordering'          => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Create default dashboard only if no dashboards exist (new installation)
        DB::table('dashboards')->insert([
            'name'              => 'Default Dashboard',
            'description'       => null,
            'page'              => null,
            'ordering'          => 0,
            'is_active'         => true,
            'is_locked'         => false,
            'dashboard_grid_id' => $gridId,
            'settings'          => null,
            'filters'           => null,
            'display_filters'   => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboards');
        Schema::dropIfExists('dashboard_grid_blocks');
        Schema::dropIfExists('dashboard_grids');
    }
};
