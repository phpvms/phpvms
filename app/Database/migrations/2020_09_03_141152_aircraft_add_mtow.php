<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `mtow` column for the max takeoff weight
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->unsignedDecimal('mtow')
                ->nullable()
                ->default(0.0)
                ->after('hex_code');
        });
    }

    public function down()
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropColumn('mtow');
        });
    }
};
