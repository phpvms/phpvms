<?php

use App\Contracts\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Turn the airport coordinates and other lat/lon coords into decimal type
 */
return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('acars', function ($table) {
            $table->decimal('lat', 10, 5)->change()->default(0.0)->nullable();
            $table->decimal('lon', 11, 5)->change()->default(0.0)->nullable();
        });

        Schema::table('airports', function ($table) {
            $table->decimal('lat', 10, 5)->change()->default(0.0)->nullable();
            $table->decimal('lon', 11, 5)->change()->default(0.0)->nullable();
        });
    }
};
