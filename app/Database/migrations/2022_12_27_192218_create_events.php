<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Add events table and update flights & pirep tables for references
return new class() extends Migration {
    public function up()
    {
        // Create events table
        Schema::create('events', function (Blueprint $table) {
            $table->integer('id');
            $table->unsignedInteger('type')->default(0);
            $table->string('name', 250);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('active')->default(false)->nullable();
            $table->timestamps();
            // Add index
            $table->primary('id');
        });

        // Update flights table
        Schema::table('flights', function (Blueprint $table) {
            $table->unsignedInteger('event_id')->nullable()->after('visible');
            $table->unsignedInteger('user_id')->nullable()->after('event_id');
        });

        // Update pireps table
        Schema::table('pireps', function (Blueprint $table) {
            $table->unsignedInteger('event_id')->nullable()->after('aircraft_id');
        });
    }
};
