<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * `acars`.`distance` was an unsigned integer and can't hold a fraction, so it
     * disagreed with `pirep_positions`.`distance` about the same quantity. Lossless:
     * unsigned int tops out well inside exact double range.
     */
    public function up(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->double('distance')->nullable()->change();
        });
    }

    /** Back to unsigned integer. Restores the schema, not the lost precision. */
    public function down(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->unsignedInteger('distance')->nullable()->change();
        });
    }
};
