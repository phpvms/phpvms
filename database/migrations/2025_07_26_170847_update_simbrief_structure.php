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
        Schema::table('users', function (Blueprint $table) {
            $table->string('simbrief_username')->nullable()->after('ivao_id');
        });

        Schema::table('simbrief', function (Blueprint $table) {
            $table->dropColumn(['acars_xml', 'ofp_xml']);

            $table->string('ofp_json_path')
                ->nullable()
                ->after('aircraft_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
