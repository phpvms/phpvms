<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a simulator-type column to `pireps`.
 *
 * Stores which simulator a flight was flown on (App\Enums\SimType, values
 * shared with the ACARS contract `SimType` proto enum). Set at prefile by both
 * the core prefile endpoint and the ACARS contract's CreatePirep.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('pireps', function (Blueprint $table): void {
            if (!Schema::hasColumn('pireps', 'sim_type')) {
                $table->unsignedTinyInteger('sim_type')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pireps', function (Blueprint $table): void {
            if (Schema::hasColumn('pireps', 'sim_type')) {
                $table->dropColumn('sim_type');
            }
        });
    }
};
