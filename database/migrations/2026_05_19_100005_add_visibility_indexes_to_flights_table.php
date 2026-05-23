<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->index('visible', 'flights_visible_index');
            $table->index(['bundle_id', 'visible'], 'flights_bundle_id_visible_index');
            $table->index(['enabled', 'bundle_id'], 'flights_enabled_bundle_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropIndex('flights_visible_index');
            $table->dropIndex('flights_bundle_id_visible_index');
            $table->dropIndex('flights_enabled_bundle_id_index');
        });
    }
};
