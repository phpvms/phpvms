<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    public function up()
    {
        Schema::create('subfleets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('airline_id')->nullable();
            $table->string('type', 50)->unique();
            $table->string('name', 50);
            $table->unsignedDecimal('cost_block_hour')->default(0)->nullable();
            $table->unsignedDecimal('cost_delay_minute')->default(0)->nullable();
            $table->unsignedTinyInteger('fuel_type')->nullable();
            $table->unsignedDecimal('ground_handling_multiplier')->nullable()->default(100);
            $table->unsignedDecimal('cargo_capacity')->nullable();
            $table->unsignedDecimal('fuel_capacity')->nullable();
            $table->unsignedDecimal('gross_weight')->nullable();
            $table->timestamps();
        });

        Schema::create('subfleet_fare', function (Blueprint $table) {
            $table->unsignedInteger('subfleet_id');
            $table->unsignedInteger('fare_id');
            $table->string('price')->nullable();
            $table->string('cost')->nullable();
            $table->string('capacity')->nullable();
            $table->timestamps();

            $table->primary(['subfleet_id', 'fare_id']);
            $table->index(['fare_id', 'subfleet_id']);
        });

        Schema::create('subfleet_rank', function (Blueprint $table) {
            $table->unsignedInteger('rank_id');
            $table->unsignedInteger('subfleet_id');
            $table->string('acars_pay')->nullable();
            $table->string('manual_pay')->nullable();

            $table->primary(['rank_id', 'subfleet_id']);
            $table->index(['subfleet_id', 'rank_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subfleets');
        Schema::dropIfExists('subfleet_fare');
        Schema::dropIfExists('subfleet_rank');
    }
};
