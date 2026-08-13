<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('simbrief_attempts', function (Blueprint $table): void {
            $table->string('static_id', 36)->primary();
            $table->unsignedInteger('user_id')->index();
            $table->string('flight_id', 36)->index();
            $table->unsignedInteger('aircraft_id')->index();
            $table->json('fare_data')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::table('simbrief', function (Blueprint $table): void {
            $table->string('static_id', 36)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('simbrief', function (Blueprint $table): void {
            $table->dropUnique(['static_id']);
            $table->dropColumn('static_id');
        });

        Schema::dropIfExists('simbrief_attempts');
    }
};
