<?php

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
        if (!Schema::hasTable('dashboards') || !Schema::hasTable('dashboard_widgets')) {
            return;
        }

        if (!Schema::hasColumn('dashboards', 'template_key')) {
            Schema::table('dashboards', function (Blueprint $table): void {
                $table->string('template_key')->nullable()->after('page');
            });
        }

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

        DB::table('dashboards')->whereNull('template_key')->update(['template_key' => 'flat-12']);

        if (Schema::hasColumn('dashboard_widgets', 'ordering') && Schema::hasColumn('dashboard_widgets', 'columns')) {
            DB::table('dashboard_widgets')
                ->select(['id', 'columns', 'ordering'])
                ->orderBy('dashboard_id')
                ->orderBy('ordering')
                ->eachById(function (object $widget): void {
                    DB::table('dashboard_widgets')->where('id', $widget->id)->update([
                        'section_slug' => 'main',
                        'x'            => 0,
                        'y'            => (int) $widget->ordering,
                        'w'            => (int) $widget->columns,
                        'h'            => 1,
                    ]);
                });
        }

        if (!collect(Schema::getIndexes('dashboard_widgets'))->contains('name', 'idx_dashboard_widgets_dashboard_section')) {
            Schema::table('dashboard_widgets', function (Blueprint $table): void {
                $table->index(['dashboard_id', 'section_slug'], 'idx_dashboard_widgets_dashboard_section');
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): never
    {
        throw new RuntimeException('The dynamic dashboard v2 schema cannot be reversed safely.');
    }
};
