<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('simbrief_aircraft')) {
            Schema::create('simbrief_aircraft', function (Blueprint $table) {
                $table->increments('id');
                $table->string('icao');
                $table->string('name');
                $table->mediumText('details')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simbrief_airframes')) {
            Schema::create('simbrief_airframes', function (Blueprint $table) {
                $table->increments('id');
                $table->string('icao');
                $table->string('name');
                $table->string('airframe_id')->nullable();
                $table->unsignedTinyInteger('source')->nullable();
                $table->mediumText('details')->nullable();
                $table->mediumText('options')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simbrief_layouts')) {
            Schema::create('simbrief_layouts', function (Blueprint $table) {
                $table->string('id');
                $table->string('name');
                $table->string('name_long');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('simbrief_aircraft');
        Schema::dropIfExists('simbrief_airframes');
        Schema::dropIfExists('simbrief_layouts');
    }
};
