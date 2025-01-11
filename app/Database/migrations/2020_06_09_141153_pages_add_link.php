<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `link` column and make the body optional. Also add a "new_window" bool
 * which determines if we open this link in a new window or not
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('body')->change()->nullable();
            $table->string('link')
                ->default('')
                ->nullable()
                ->after('body');

            $table->boolean('new_window')->default(false);
        });
    }

    public function down()
    {
        Schema::table('fares', function (Blueprint $table) {
            $table->dropColumn('link');
            $table->dropColumn('new_window');
        });
    }
};
