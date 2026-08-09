<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * One row per PIREP holding its last-known position. Presence of a row is map
     * membership. Column names follow `acars` and `pireps`, not the wire protocol,
     * so units come from DistanceCast/FuelCast. Everything but the timestamps is NOT NULL.
     */
    public function up(): void
    {
        if (Schema::hasTable('pirep_positions')) {
            return;
        }

        Schema::create('pirep_positions', function (Blueprint $table): void {
            // Matching `pireps`: MySQL rejects an FK across mismatched collations.
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            // The parent key is the primary key - one row per PIREP is the point.
            $table->string('pirep_id', 36)->primary();

            // Denormalised for pilot-scoped queries. Safe while a PIREP's owner
            // cannot change.
            $table->unsignedInteger('user_id')->index();

            // PirepPhase, same storage as `pireps`.`status`.
            $table->string('phase', 3)->default('SCH');

            $table->decimal('lat', 10, 5)->default(0);
            $table->decimal('lon', 11, 5)->default(0);
            $table->unsignedSmallInteger('heading')->default(0);

            // Plain DOUBLE: UNSIGNED is deprecated in MySQL 8.0.17, and `vs` is signed.
            $table->double('distance')->default(0);
            $table->double('altitude_agl')->default(0);
            $table->double('altitude_msl')->default(0);
            $table->double('vs')->default(0);

            $table->unsignedInteger('gs')->default(0);
            $table->unsignedInteger('ias')->default(0);

            // Minutes, matching `pireps`.`flight_time`.
            $table->unsignedInteger('flight_time')->default(0);
            $table->decimal('fuel_used')->default(0);

            // `updated_at` is the liveness clock: position batches only, unlike
            // `pireps`.`updated_at`, which any write bumps.
            $table->timestamps();

            // At create time, so it lands on SQLite too - unlike the `acars` one.
            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_positions');
    }
};
