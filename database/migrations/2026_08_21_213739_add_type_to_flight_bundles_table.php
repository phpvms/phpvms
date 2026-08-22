<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a bundle IS, not just what it groups. `flights` is an ordinary schedule
 * (every existing row, via the default — no backfill); `tour` is an ordered
 * chain of legs a pilot commits to as a unit. See App\Enums\BundleType.
 *
 * No index: the column has two values over a table of tens of rows, so the
 * list-page filter reads it faster with a scan than with an index.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('flight_bundles', 'type')) {
            return;
        }

        Schema::table('flight_bundles', function (Blueprint $table): void {
            $table->string('type', 16)->default('flights')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('flight_bundles', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
