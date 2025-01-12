<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('owner_type')->nullable();
            $table->string('owner_id', 36)->nullable();

            $table->index(['owner_type', 'owner_id']);
        });
    }
};
