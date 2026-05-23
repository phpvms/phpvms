<?php

use App\Support\FlightTimeBackfiller;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Data migration: populate the new `departure_time` / `arrival_time`
     * structured TIME columns from the legacy free-form `dpt_time` /
     * `arr_time` strings via FlightTimeParser.
     *
     * Paired with schema migration
     * `database/migrations/2026_05_19_000000_add_time_columns_to_flights_table.php`
     * which adds the columns. Runs chunked (1000 rows/iteration) to keep
     * memory bounded on large flights tables. Parse failures are logged and
     * leave the target column NULL.
     */
    public function up(): void
    {
        FlightTimeBackfiller::run();
    }
};
