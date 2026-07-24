<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the per-aircraft `simbrief_type` override column.
 *
 * The SimBrief airframe type is configured at the subfleet level
 * (subfleets.simbrief_type); the aircraft-level override is removed, so lookups
 * fall back to the subfleet value (then the aircraft ICAO).
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table): void {
            if (Schema::hasColumn('aircraft', 'simbrief_type')) {
                $table->dropColumn('simbrief_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aircraft', function (Blueprint $table): void {
            if (!Schema::hasColumn('aircraft', 'simbrief_type')) {
                $table->string('simbrief_type', 25)->nullable();
            }
        });
    }
};
