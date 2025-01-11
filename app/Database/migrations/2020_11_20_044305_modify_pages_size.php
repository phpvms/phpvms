<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change the pages body column type to a Medium Text, max size of 16MB
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->mediumText('body')->change()->nullable();
        });
    }
};
