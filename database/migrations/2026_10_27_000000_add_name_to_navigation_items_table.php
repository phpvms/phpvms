<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give each navigation item an admin-facing name, separate from its label.
 *
 * The label is what the site shows, and may be a translation key such as
 * `common.dashboard`; the name is what an admin reads in the menu editor.
 * Existing rows take their label as their name, so nothing reads blank.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('navigation_items') || Schema::hasColumn('navigation_items', 'name')) {
            return;
        }

        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('navigation_items')->whereNull('name')->update(['name' => DB::raw('label')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('navigation_items', 'name')) {
            Schema::table('navigation_items', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
    }
};
