<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ACARS-written custom fields carry a value type (NUMBER/TEXT/TIMESTAMP/BOOLEAN)
 * and an AIXM unit code (FT, KT, ...). Both nullable — writes that only set
 * name/value/source leave them null, meaning an untyped plain-string value.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('pirep_field_values', function (Blueprint $table): void {
            $table->string('type', 20)->nullable()->after('value');
            $table->string('units', 20)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('pirep_field_values', function (Blueprint $table): void {
            $table->dropColumn(['type', 'units']);
        });
    }
};
