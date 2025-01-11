<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `load_factor` and `load_factor_variance` columns to the expenses table
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->decimal('load_factor', 5, 2)
                ->nullable()
                ->after('flight_type');

            $table->decimal('load_factor_variance', 5, 2)
                ->nullable()
                ->after('load_factor');
        });
    }

    public function down()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn('load_factor');
            $table->dropColumn('load_factor_variance');
        });
    }
};
