<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * One row per PIREP holding its last-known position.
     *
     * The live map used to answer "where is this aircraft now" by resolving the
     * newest `acars` FLIGHT_PATH row per PIREP — a latest-of-many over a table
     * that grows for the life of the install and is never pruned. This table
     * replaces that with a lookup whose row count is the number of flights on
     * the map.
     *
     * Presence of a row *is* map membership. There is no state column and no
     * expiry timestamp to compare against, because the read path does no
     * filtering: rows are created at prefile, maintained by the ACARS batch
     * endpoint, and removed by `PirepPositionExpiration` or by cancellation.
     *
     * Column names follow `acars` and `pireps` rather than the wire protocol —
     * `distance` not `distance_nm`, `gs` not `gs_kt` — because units are
     * expressed through DistanceCast/FuelCast, which is what makes phpVMS's
     * configurable display units work.
     *
     * Everything except the timestamps is NOT NULL. A prefiled aircraft really
     * does have zero groundspeed and has really flown zero miles, so the seeded
     * values are values rather than placeholders, and no consumer has to
     * distinguish "not yet reported" from "zero".
     */
    public function up(): void
    {
        if (Schema::hasTable('pirep_positions')) {
            return;
        }

        Schema::create('pirep_positions', function (Blueprint $table): void {
            // Matching `pireps` exactly: MySQL rejects a foreign key whose two
            // sides disagree on charset or collation.
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            // One row per PIREP is the whole point, so the parent key is the
            // primary key rather than a column beside a surrogate one.
            $table->string('pirep_id', 36)->primary();

            // Denormalised so "is this pilot flying" resolves without joining
            // `pireps`. Safe because a PIREP's owner does not change; if that
            // ever becomes false this column turns into a correctness hazard.
            $table->unsignedInteger('user_id')->index();

            // PirepPhase, same storage as `pireps`.`status`.
            $table->string('phase', 3)->default('SCH');

            $table->decimal('lat', 10, 5)->default(0);
            $table->decimal('lon', 11, 5)->default(0);
            $table->unsignedSmallInteger('heading')->default(0);

            // Plain DOUBLE: DOUBLE UNSIGNED is deprecated as of MySQL 8.0.17,
            // and `vs` has to hold negatives anyway — a descent is a negative
            // vertical speed.
            $table->double('distance')->default(0);
            $table->double('altitude_agl')->default(0);
            $table->double('altitude_msl')->default(0);
            $table->double('vs')->default(0);

            $table->unsignedInteger('gs')->default(0);
            $table->unsignedInteger('ias')->default(0);

            // Minutes, matching `pireps`.`flight_time`.
            $table->unsignedInteger('flight_time')->default(0);
            $table->decimal('fuel_used')->default(0);

            // `updated_at` is the liveness clock the expiration job reads. It
            // moves on position batches only, which is the point of not using
            // `pireps`.`updated_at` — that one is bumped by any write, so an
            // admin edit made a dead flight look alive.
            $table->timestamps();

            // Declared at create time, so it lands on every platform including
            // SQLite. The equivalent constraint on `acars` can only be added by
            // ALTER, which is why it needs a migration of its own.
            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_positions');
    }
};
