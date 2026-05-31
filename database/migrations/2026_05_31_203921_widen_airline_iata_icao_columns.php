<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('airlines', function (Blueprint $table): void {
            $table->string('icao', 12)->change();
            $table->string('iata', 12)->nullable()->change();
            $table->string('country', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table): void {
            $table->string('icao', 5)->change();
            $table->string('iata', 5)->nullable()->change();
            $table->string('country', 2)->nullable()->change();
        });
    }
};
