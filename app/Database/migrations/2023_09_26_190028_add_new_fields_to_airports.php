<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        if (!Schema::hasColumns('airports', ['elevation', 'region'])) {
            Schema::table('airports', function (Blueprint $table) {
                $table->integer('elevation')->nullable()->after('lon');
                $table->string('region', 150)->nullable()->after('location');
            });
        }
    }
};
