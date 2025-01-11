<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `anumeric_callsign` column for Alphanumeric Callsign to be assigned for a flight
 * Exp DLH78BF, THY8EA, OGE1978
 * According to FAA and EASA, callsigns must be maximum 7 chars in which first 3 chars is
 * airline ICAO code remaining rest can be used freely according to airline's choices
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('callsign', 4)
                ->nullable()
                ->after('flight_number');
        });
    }

    public function down()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn('callsign');
        });
    }
};
