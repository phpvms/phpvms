<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Increase Airport ICAO size to 5 chars
 * https://github.com/nabeelio/phpvms/issues/1052
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->string('iata', 5)->change();
            $table->string('icao', 5)->change();
        });

        Schema::table('pireps', function (Blueprint $table) {
            $table->string('dpt_airport_id', 5)->change();
            $table->string('arr_airport_id', 5)->change();
            $table->string('alt_airport_id', 5)->change();
        });
    }
};
