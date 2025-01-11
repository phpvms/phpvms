<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a SimBrief Type to subfleet
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('subfleets', function (Blueprint $table) {
            $table->string('simbrief_type', 20)
                ->nullable()
                ->after('type');
        });
    }
};
