<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Fines an addon resolved for the pirep at file time, e.g. from SOP rule
     * violations. Snapshotted here rather than looked up during finance
     * processing so that editing or deleting the rule that caused a fine can't
     * change — or break — a past pirep's recalculation.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pirep_archive', 'fines')) {
            return;
        }

        Schema::table('pirep_archive', function (Blueprint $table): void {
            $table->json('fines')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pirep_archive', function (Blueprint $table): void {
            $table->dropColumn('fines');
        });
    }
};
