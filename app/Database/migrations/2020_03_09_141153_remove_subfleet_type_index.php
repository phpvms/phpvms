<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the unique index from subfleets.type
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('subfleets', function (Blueprint $table) {
            $table->dropUnique(['type']);
        });
    }
};
