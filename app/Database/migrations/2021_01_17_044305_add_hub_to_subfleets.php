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
        Schema::table('subfleets', function (Blueprint $table) {
            $table->string('hub_id', 4)
                ->nullable()
                ->after('airline_id');
        });
    }
};
