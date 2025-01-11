<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change the downloads link size
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('files', function (Blueprint $table) {
            $table->mediumText('disk')->change()->nullable();
            $table->mediumText('path')->change()->nullable();
        });
    }
};
