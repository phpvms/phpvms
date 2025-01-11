<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds deleted_at fields to tables with SoftDelete trait
return new class() extends Migration {
    public function up()
    {
        // Aircraft
        Schema::table('aircraft', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Airline
        Schema::table('airlines', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Airport
        Schema::table('airports', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Award
        Schema::table('awards', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Fare
        if (!Schema::hasColumn('fares', 'deleted_at')) {
            Schema::table('fares', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Flight
        Schema::table('flights', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Pirep
        Schema::table('pireps', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Rank
        Schema::table('ranks', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Subfleet
        Schema::table('subfleets', function (Blueprint $table) {
            $table->softDeletes();
        });

        // User table already have required field
        // Schema::table('users', function (Blueprint $table) { $table->softDeletes(); });
    }
};
