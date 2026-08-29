<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups the N bids a tour creates, so cancelling or expiring one is a single
 * statement over one index. Existing bids get null and behave exactly as before.
 *
 * Grouped by the run and not by `bundle_id` on purpose: a pilot's second attempt
 * at a tour must not be conflated with their first.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bids', 'user_tour_id')) {
            return;
        }

        Schema::table('bids', function (Blueprint $table): void {
            $table->string('user_tour_id', 36)->nullable()->after('aircraft_id')->index();

            $table->foreign('user_tour_id')->references('id')->on('user_tours')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table): void {
            $table->dropForeign(['user_tour_id']);
            $table->dropColumn('user_tour_id');
        });
    }
};
