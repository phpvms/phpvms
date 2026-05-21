<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('subfleets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('cruise_speed')->nullable()->after('gross_weight');
            $table->unsignedInteger('max_range_nm')->nullable()->after('cruise_speed');
            $table->string('route_types', 64)->nullable()->after('max_range_nm');
        });
    }

    public function down(): void
    {
        Schema::table('subfleets', function (Blueprint $table): void {
            $table->dropColumn(['cruise_speed', 'max_range_nm', 'route_types']);
        });
    }
};
