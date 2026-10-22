<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per pilot's run through a tour bundle — created when they bid it,
 * mutated as legs are filed, kept forever afterwards so awards, stats and pilot
 * history can read a run that finished, was abandoned or expired.
 *
 * Shaped after `pirep_archive` (2026_08_05_002358): nullable, FK-less reference
 * columns and snapshotted text, so a deleted or renamed parent leaves the record
 * readable. It is NOT an archive though — `pirep_archive` is written once at file
 * time, this is a live lifecycle table updated on every leg.
 *
 * `user_id` is the one real FK, and cascades: UserService::removeUser() only
 * hard-deletes a pilot with no PIREPs, so cascading can only lose the tours of
 * someone who never flew. A soft-deleted pilot keeps theirs.
 *
 * `name` / `description` are snapshots of the bundle's, on purpose. A bundle
 * renamed mid-tour leaves running tours showing the name the pilot signed up
 * for, and lets an award name a bundle that no longer exists.
 *
 * `pirep_id` looks backward — the most recent PIREP touching the tour in
 * whatever state it is, so callers read that PIREP's own state to tell "airborne
 * on leg 3" from "parked after leg 2". `flight_id` looks forward — the leg in
 * play, at route_leg = legs_completed + 1, null once the tour completes.
 *
 * `legs` is JSON rather than a `user_tour_legs` table because consumers read a
 * whole tour at a time. Normalize it if awards ever need cross-leg queries.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_tours')) {
            return;
        }

        Schema::create('user_tours', function (Blueprint $table): void {
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            // Nano ID from App\Traits\HasNanoIds, which is 16 chars. Sized 36 to
            // match every other string key in this schema.
            $table->string('id', 36)->primary();

            $table->unsignedInteger('user_id');

            // FK-less on purpose (see the class docblock). Native types, not
            // strings: `flight_bundles.id` is a bigint and `aircraft.id` an int,
            // and a varchar here would make the (bundle_id, status) index below
            // unusable for the live-runs panel that reads it.
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedInteger('aircraft_id')->nullable();
            $table->string('pirep_id', 36)->nullable();
            $table->string('flight_id', 36)->nullable();

            // Snapshots of the bundle's, not a cache of them.
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status', 16)->default('in_progress');

            $table->unsignedInteger('legs_total')->default(0);
            $table->unsignedInteger('legs_completed')->default(0);

            // [{flight_id, route_leg, pirep_id, filed_at}, ...] in leg order.
            $table->json('legs')->nullable();

            // Nullable although the service always sets it: with MySQL's
            // explicit_defaults_for_timestamp off, the first NOT NULL TIMESTAMP
            // with no default silently gets ON UPDATE CURRENT_TIMESTAMP — which
            // would rewrite started_at on every leg filed.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['bundle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tours');
    }
};
