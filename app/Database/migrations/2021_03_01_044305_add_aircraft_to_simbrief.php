<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a hub to the subfleet is
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('simbrief', function (Blueprint $table) {
            $table->unsignedInteger('aircraft_id')
                ->nullable()
                ->after('pirep_id');

            // Temp column to hold the calculated fare data for the API
            // Remove this once the prefile to acars feature is completed
            $table->mediumText('fare_data')->nullable()->after('ofp_xml');
        });
    }
};
