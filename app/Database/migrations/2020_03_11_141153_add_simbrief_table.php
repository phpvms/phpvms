<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a table to store the Simbrief data
 */
return new class() extends Migration {
    public function up()
    {
        Schema::create('simbrief', function (Blueprint $table) {
            $table->string('id', 36); // The OFP ID
            $table->unsignedInteger('user_id');
            $table->string('flight_id', 36)->nullable();
            $table->string('pirep_id', 36)->nullable();
            $table->mediumText('acars_xml');
            $table->mediumText('ofp_xml');
            $table->timestamps();

            $table->primary('id');
            $table->index(['user_id', 'flight_id']);
            $table->index('pirep_id');
            $table->unique('pirep_id'); // Can only belong to a single PIREP
        });
    }

    public function down()
    {
        Schema::drop('simbrief');
    }
};
