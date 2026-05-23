<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Adds the structured TIME columns. The accompanying backfill from legacy
     * free-form `dpt_time` / `arr_time` strings runs as a separate data
     * migration (see `database/migrations_data/2026_05_19_000000_backfill_flight_times.php`)
     * so schema deploys stay fast and data work is explicit.
     */
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->time('departure_time')->nullable()->after('dpt_time');
            $table->time('arrival_time')->nullable()->after('arr_time');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropColumn('departure_time');
            $table->dropColumn('arrival_time');
        });
    }
};
