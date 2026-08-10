<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * The create migration first shipped `pirep_archive` with a single `data`
     * json column, then was rewritten in place to split it into
     * flight/aircraft/simbrief/navlog — but it early-returns on hasTable(), so
     * a database migrated in between keeps the old shape and every archive
     * write fails on the missing columns. The archive is a derived snapshot,
     * so reshape the columns here and let `php artisan pireps:archive-backfill`
     * repopulate them.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('pirep_archive', 'data')) {
            return;
        }

        Schema::table('pirep_archive', function (Blueprint $table): void {
            $table->json('flight')->nullable();
            $table->json('aircraft')->nullable();
            $table->json('simbrief')->nullable();
            $table->json('navlog')->nullable();
            $table->dropColumn('data');
        });
    }

    public function down(): void
    {
        // Nothing to undo: the split columns are the shape the create
        // migration produces on any fresh install.
    }
};
