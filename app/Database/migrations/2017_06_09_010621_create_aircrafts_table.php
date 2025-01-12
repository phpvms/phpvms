<?php

use App\Contracts\Migration;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    public function up()
    {
        Schema::create('aircraft', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('subfleet_id');
            $table->string('icao', 4)->nullable();
            $table->string('iata', 4)->nullable();
            $table->string('airport_id', 5)->nullable();
            $table->timestamp('landing_time')->nullable();
            $table->string('name', 50);
            $table->string('registration', 10)->nullable();
            $table->string('hex_code', 10)->nullable();
            $table->unsignedDecimal('zfw')->nullable()->default(0);
            $table->unsignedBigInteger('flight_time')->nullable()->default(0);
            $table->char('status', 1)->default(AircraftStatus::ACTIVE);
            $table->unsignedTinyInteger('state')->default(AircraftState::PARKED);
            $table->timestamps();

            $table->unique('registration');
            $table->index('airport_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aircraft');
    }
};
