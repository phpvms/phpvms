<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->mediumText('notes')->nullable()->after('remember_token');
        });
    }
};
