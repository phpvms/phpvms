<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    public function up()
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->increments('id');
            $table->string('icao', 5);
            $table->string('iata', 5)->nullable();
            $table->string('name', 50);
            $table->string('country', 2)->nullable();
            $table->string('logo', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('total_flights')->nullable()->default(0);
            $table->unsignedBigInteger('total_time')->nullable()->default(0);
            $table->timestamps();

            $table->index('icao');
            $table->unique('icao');

            $table->index('iata');
            $table->unique('iata');
        });
    }

    public function down()
    {
        Schema::dropIfExists('airlines');
    }
};
