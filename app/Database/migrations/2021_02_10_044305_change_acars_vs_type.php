<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change the vertical speed for the acars table to a double
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('acars', function (Blueprint $table) {
            $table->float('vs')->change()->default(0.0)->nullable();
        });
    }
};
