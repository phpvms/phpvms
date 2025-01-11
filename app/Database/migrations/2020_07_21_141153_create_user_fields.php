<?php

use App\Contracts\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add two tables for holding user fields and the values
 */
return new class() extends Migration {
    public function up()
    {
        /*
         * Hold a master list of fields
         */
        Schema::create('user_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('show_on_registration')->default(false)->nullable();
            $table->boolean('required')->default(false)->nullable();
            $table->boolean('private')->default(false)->nullable();
            $table->boolean('active')->default(true)->nullable();
            $table->timestamps();
        });

        /*
         * The values for the actual fields
         */
        Schema::create('user_field_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_field_id');
            $table->string('user_id', Model::ID_MAX_LENGTH);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['user_field_id', 'user_id']);
        });
    }
};
