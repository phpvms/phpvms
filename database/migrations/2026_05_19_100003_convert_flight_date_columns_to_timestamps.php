<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Convert flights.start_date and flights.end_date from DATE to TIMESTAMP (UTC).
     *
     * Rationale: dates are interpreted in UTC by the visibility cron. Storing as
     * TIMESTAMP makes the timezone interpretation explicit and lets the Filament
     * UI present them in the operator's local timezone while persisting UTC.
     */
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->timestamp('start_date')->nullable()->change();
            $table->timestamp('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }
};
