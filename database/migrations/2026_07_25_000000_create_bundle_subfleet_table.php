<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Bundle-level subfleet defaults, inherited by the bundle's flights when a
     * flight has no `flight_subfleet` pins of its own. See
     * Flight::accessibleSubfleetsFor().
     *
     * Composite primary key, matching the `subfleet_rank` and
     * `typerating_subfleet` pivots. A bare UNIQUE index would be equivalent on
     * MySQL but not on PostgreSQL, where a table without a PK cannot publish
     * UPDATE/DELETE under logical replication. The reverse index covers
     * subfleet -> bundles.
     *
     * Column types must match their referents exactly or MySQL 8 rejects the
     * FK: `flight_bundles.id` is `id()` (bigint unsigned) while `subfleets.id`
     * is `increments()` (int unsigned).
     */
    public function up(): void
    {
        Schema::create('bundle_subfleet', function (Blueprint $table): void {
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedInteger('subfleet_id');

            $table->foreign('bundle_id')->references('id')->on('flight_bundles')->cascadeOnDelete();
            $table->foreign('subfleet_id')->references('id')->on('subfleets')->cascadeOnDelete();

            $table->primary(['bundle_id', 'subfleet_id']);
            $table->index(['subfleet_id', 'bundle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_subfleet');
    }
};
