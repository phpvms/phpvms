<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up()
    {
        Schema::table('pirep_fields', function (Blueprint $table) {
            $table->string('description')->nullable()->after('slug');
        });
    }
};
