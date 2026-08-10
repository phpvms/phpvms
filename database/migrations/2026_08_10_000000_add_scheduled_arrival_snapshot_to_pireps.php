<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pireps', 'scheduled_arrival_at')) {
            Schema::table('pireps', function (Blueprint $table): void {
                $table->dateTime('scheduled_arrival_at')->nullable();
            });
        }

        if (!Schema::hasColumn('pirep_archive', 'scheduled_arrival_at')) {
            Schema::table('pirep_archive', function (Blueprint $table): void {
                $table->dateTime('scheduled_arrival_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pirep_archive', 'scheduled_arrival_at')) {
            Schema::table('pirep_archive', function (Blueprint $table): void {
                $table->dropColumn('scheduled_arrival_at');
            });
        }

        if (Schema::hasColumn('pireps', 'scheduled_arrival_at')) {
            Schema::table('pireps', function (Blueprint $table): void {
                $table->dropColumn('scheduled_arrival_at');
            });
        }
    }
};
