<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('acars', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable();
        });

        Schema::create('acars_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->index();
            $table->string('name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acars', function (Blueprint $table) {
            //
        });
    }
};
