<?php

use App\Contracts\Migration;
use App\Contracts\Model;
use App\Models\Enums\FlightType;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    public function up()
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->string('id', Model::ID_MAX_LENGTH);
            $table->unsignedInteger('airline_id');
            $table->unsignedInteger('flight_number');
            $table->string('route_code', 5)->nullable();
            $table->unsignedInteger('route_leg')->nullable();
            $table->string('dpt_airport_id', 4);
            $table->string('arr_airport_id', 4);
            $table->string('alt_airport_id', 4)->nullable();
            $table->string('dpt_time', 10)->nullable();
            $table->string('arr_time', 10)->nullable();
            $table->unsignedInteger('level')->nullable()->default(0);
            $table->unsignedDecimal('distance')->nullable()->default(0.0);
            $table->unsignedInteger('flight_time')->nullable();
            $table->char('flight_type', 1)->default(FlightType::SCHED_PAX);
            $table->text('route')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('scheduled')->default(false)->nullable();
            $table->unsignedTinyInteger('days')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('has_bid')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('visible')->default(true); // used by the cron
            $table->timestamps();

            $table->primary('id');

            $table->index('flight_number');
            $table->index('dpt_airport_id');
            $table->index('arr_airport_id');
        });

        Schema::create('flight_fare', function (Blueprint $table) {
            $table->string('flight_id', Model::ID_MAX_LENGTH);
            $table->unsignedInteger('fare_id');
            $table->string('price', 10)->nullable();
            $table->string('cost', 10)->nullable();
            $table->string('capacity', 10)->nullable();
            $table->timestamps();

            $table->primary(['flight_id', 'fare_id']);
        });

        /*
         * Hold a master list of fields
         */
        Schema::create('flight_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50);
            $table->string('slug', 50)->nullable();
        });

        /*
         * The values for the actual fields
         */
        Schema::create('flight_field_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('flight_id', Model::ID_MAX_LENGTH);
            $table->string('name', 50);
            $table->string('slug', 50)->nullable();
            $table->text('value');
            $table->timestamps();

            $table->index('flight_id');
        });

        Schema::create('flight_subfleet', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('subfleet_id');
            $table->string('flight_id', Model::ID_MAX_LENGTH);

            $table->index(['subfleet_id', 'flight_id']);
            $table->index(['flight_id', 'subfleet_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('flight_fields');
        Schema::drop('flight_fare');
        Schema::drop('flight_subfleet');
        Schema::drop('flights');
    }
};
