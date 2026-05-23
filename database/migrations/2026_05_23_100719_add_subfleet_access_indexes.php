<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table): void {
            $table->index(['subfleet_id']);
        });

        Schema::table('bids', function (Blueprint $table): void {
            $table->index(['aircraft_id']);
        });

        Schema::table('typerating_user', function (Blueprint $table): void {
            $table->dropIndex(['typerating_id', 'user_id']);
            $table->index(['user_id', 'typerating_id']);
        });

        Schema::table('typerating_subfleet', function (Blueprint $table): void {
            $table->dropIndex(['typerating_id', 'subfleet_id']);
        });
    }

    public function down(): void
    {
        Schema::table('typerating_subfleet', function (Blueprint $table): void {
            $table->index(['typerating_id', 'subfleet_id']);
        });

        Schema::table('typerating_user', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'typerating_id']);
            $table->index(['typerating_id', 'user_id']);
        });

        Schema::table('bids', function (Blueprint $table): void {
            $table->dropIndex(['aircraft_id']);
        });

        Schema::table('aircraft', function (Blueprint $table): void {
            $table->dropIndex(['subfleet_id']);
        });
    }
};
