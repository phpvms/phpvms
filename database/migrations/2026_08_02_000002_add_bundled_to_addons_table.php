<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundled addons ship with phpVMS itself (e.g. the Addon Manager). They are
 * enabled by default and cannot be disabled or deleted from the panel — the
 * flag is the guard. Non-bundled addons default to false.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table): void {
            $table->boolean('bundled')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('bundled');
        });
    }
};
