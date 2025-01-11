<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a hub to the subfleet is
 */
return new class() extends Migration {
    public function up()
    {
        Schema::create('kvp', function (Blueprint $table) {
            $table->string('key')->index();
            $table->string('value');
        });
    }
};
