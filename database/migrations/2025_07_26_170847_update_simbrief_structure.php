<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Guarded one by one: a run cut short between them must not fail on the column it already added.
        if (!Schema::hasColumn('users', 'simbrief_username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('simbrief_username')->nullable()->after('ivao_id');
            });
        }

        if (!Schema::hasColumn('simbrief', 'ofp_json_path')) {
            Schema::table('simbrief', function (Blueprint $table): void {
                $table->string('ofp_json_path')
                    ->nullable()
                    ->after('aircraft_id');
            });
        }

        // The 7.x XML stays for the simbrief_ofps post-upgrade task; 8.x never writes it.
        foreach (['acars_xml', 'ofp_xml'] as $column) {
            if (Schema::hasColumn('simbrief', $column)) {
                Schema::table('simbrief', function (Blueprint $table) use ($column): void {
                    $table->longText($column)->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
