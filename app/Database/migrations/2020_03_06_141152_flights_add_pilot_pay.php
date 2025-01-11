<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `pilot_pay` column for a fixed amount to pay to a pilot for a flight
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->decimal('pilot_pay')
                ->nullable()
                ->after('route');
        });
    }

    public function down()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn('pilot_pay');
        });
    }
};
