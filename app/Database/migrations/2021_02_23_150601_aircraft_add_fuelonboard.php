<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `fuel_onboard` column for recording what is left in tanks
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->unsignedDecimal('fuel_onboard')
                ->nullable()
                ->default(0.0)
                ->after('zfw');
        });
    }
};
