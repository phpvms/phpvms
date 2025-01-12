<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        /*
         * See for defs, modify/update based on this
         * https://github.com/skiselkov/openfmc/blob/master/airac.h
         */
        Schema::create('navdata', function (Blueprint $table) {
            $table->string('id', 5);
            $table->string('name', 24);
            $table->unsignedInteger('type');
            $table->float('lat', 7, 4)->nullable()->default(0.0);
            $table->float('lon', 7, 4)->nullable()->default(0.0);
            $table->string('freq', 7)->nullable();

            $table->primary(['id', 'name']);
            $table->index('id');
            $table->index('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('navdata');
    }
};
