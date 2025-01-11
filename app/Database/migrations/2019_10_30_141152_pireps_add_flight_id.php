<?php

use App\Contracts\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `flight_id` column to the PIREPs table
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('pireps', function (Blueprint $table) {
            $table->string('flight_id', Model::ID_MAX_LENGTH)->nullable()->after('aircraft_id');
        });
    }
};
