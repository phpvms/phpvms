<?php

use App\Support\FlightTimeBackfiller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Adds the structured TIME columns and backfills them from the legacy
     * free-form `dpt_time` / `arr_time` strings using FlightTimeParser.
     *
     * Backfill runs inline (chunked) so admins don't need to invoke a separate
     * artisan command after migrate. Parse failures are logged via the standard
     * Laravel log facade and leave the new column NULL.
     */
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->time('departure_time')->nullable()->after('dpt_time');
            $table->time('arrival_time')->nullable()->after('arr_time');
        });

        FlightTimeBackfiller::run();
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropColumn('departure_time');
            $table->dropColumn('arrival_time');
        });
    }
};
