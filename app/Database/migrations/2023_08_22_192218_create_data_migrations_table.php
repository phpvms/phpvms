<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Add events table and update flights & pirep tables for references
return new class() extends Migration {
    public function up()
    {
        // Create events table
        if (!Schema::hasTable('migrations_data')) {
            Schema::create('migrations_data', function (Blueprint $table) {
                $table->increments('id');
                $table->string('migration', 191);
                $table->integer('batch');
            });
        }
    }
};
