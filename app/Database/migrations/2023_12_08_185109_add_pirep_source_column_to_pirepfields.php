<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::table('pirep_fields', function (Blueprint $table) {
            $table->tinyInteger('pirep_source')->nullable()->default(3)->after('required');
        });
    }
};
