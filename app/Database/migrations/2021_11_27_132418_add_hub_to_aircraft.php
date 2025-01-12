<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->string('hub_id', 5)->nullable()->after('airport_id');
        });
    }
};
