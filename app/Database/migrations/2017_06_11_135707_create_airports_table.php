<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    public function up()
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->string('id', 4)->primary();
            $table->string('iata', 4)->nullable();
            $table->string('icao', 4);
            $table->string('name', 100);
            $table->string('location', 100)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('hub')->default(false);
            $table->float('lat', 7, 4)->nullable()->default(0.0);
            $table->float('lon', 7, 4)->nullable()->default(0.0);
            $table->unsignedDecimal('ground_handling_cost')->nullable()->default(0);
            $table->unsignedDecimal('fuel_100ll_cost')->nullable()->default(0);
            $table->unsignedDecimal('fuel_jeta_cost')->nullable()->default(0);
            $table->unsignedDecimal('fuel_mogas_cost')->nullable()->default(0);

            $table->index('icao');
            $table->index('iata');
            $table->index('hub');
        });
    }

    public function down()
    {
        Schema::dropIfExists('airports');
    }
};
