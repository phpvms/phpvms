<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen every airport id column to 16 characters.
 *
 * Airports key on their ICAO code today, which is what 2025_02_04_165053 sized
 * these columns for. The plan is to move them onto generated ids -- a nanoid at
 * 16 characters -- and leave `icao` as an ordinary attribute, so the id columns
 * need the room before anything can start writing one.
 *
 * Only id columns move. `airports.icao` and `airports.iata` stay as they are;
 * they hold real codes, not ids.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table): void {
            $table->string('airport_id', 16)->nullable()->change();
            $table->string('hub_id', 16)->nullable()->change();
        });

        Schema::table('airports', function (Blueprint $table): void {
            $table->string('id', 16)->change();
        });

        Schema::table('flights', function (Blueprint $table): void {
            $table->string('dpt_airport_id', 16)->change();
            $table->string('arr_airport_id', 16)->change();
            $table->string('alt_airport_id', 16)->nullable()->change();
        });

        Schema::table('pireps', function (Blueprint $table): void {
            $table->string('dpt_airport_id', 16)->change();
            $table->string('arr_airport_id', 16)->change();
            $table->string('alt_airport_id', 16)->nullable()->change();
        });

        Schema::table('subfleets', function (Blueprint $table): void {
            $table->string('hub_id', 16)->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('home_airport_id', 16)->nullable()->change();
            $table->string('curr_airport_id', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversed on purpose: narrowing back to 8 would truncate any id
        // that has already used the extra room.
    }
};
