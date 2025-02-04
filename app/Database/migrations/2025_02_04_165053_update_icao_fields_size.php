<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
           $table->string('airport_id', 8)->nullable()->change();
           $table->string('hub_id', 8)->nullable()->change();
        });

        Schema::table('airports', function (Blueprint $table) {
            $table->string('id', 8)->change();
            $table->string('icao', 8)->change();
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->string('dpt_airport_id', 8)->change();
            $table->string('arr_airport_id', 8)->change();
            $table->string('alt_airport_id', 8)->nullable()->change();
        });

        Schema::table('pireps', function (Blueprint $table) {
            $table->string('dpt_airport_id', 8)->change();
            $table->string('arr_airport_id', 8)->change();
            $table->string('alt_airport_id', 8)->nullable()->change();
        });

        Schema::table('subfleets', function (Blueprint $table) {
            $table->string('hub_id', 8)->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('home_airport_id', 8)->nullable()->change();
            $table->string('curr_airport_id', 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        //
    }
};
