<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->boolean('active')->default(true)->nullable()->after('ref_model_params');
        });
    }
};
