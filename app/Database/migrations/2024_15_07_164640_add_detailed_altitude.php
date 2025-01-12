<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('acars', function (Blueprint $table) {
            $table->renameColumn('altitude', 'altitude_agl');
        });

        Schema::table('acars', function (Blueprint $table) {
            $table->decimal('altitude_agl')->default(0.0)->change();
            $table->decimal('altitude_msl')->nullable()->default(0.0)->after('altitude_agl');
        });
    }

    public function down(): void
    {
    }
};
