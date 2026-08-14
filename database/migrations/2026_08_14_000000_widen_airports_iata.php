<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `airports.iata` from 5 to 8 characters.
 *
 * ICAO/IATA codes are capped at 8 across the app rather than pinned to their
 * real-world 2/3/4-character lengths, so operators can use the pseudo-codes
 * their networks actually fly. Every other column already allows at least that:
 * `airlines.icao`/`iata` and `aircraft.icao`/`iata` went to 12 in
 * `2026_05_31_203921_widen_and_fix_various_columns.php`, and `airports.icao`
 * plus `airports.id` went to 8 in `2025_02_04_165053_update_icao_fields_size.php`.
 * `airports.iata` was missed by both and was still at its original 5
 * (`2025_01_13_003704_create_phpvms_table.php:183`).
 *
 * A new migration rather than an edit to either of those: this repo never edits
 * a shipped migration, since existing installs have already run it.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('airports') && Schema::hasColumn('airports', 'iata')) {
            Schema::table('airports', function (Blueprint $table): void {
                $table->string('iata', 8)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('airports') && Schema::hasColumn('airports', 'iata')) {
            Schema::table('airports', function (Blueprint $table): void {
                $table->string('iata', 5)->nullable()->change();
            });
        }
    }
};
