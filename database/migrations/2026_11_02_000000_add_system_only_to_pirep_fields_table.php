<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a PIREP field be marked as system-only.
 *
 * A system-only field is set by server code (an addon, a listener) and never
 * from pilot input: the PIREP form and the ACARS API ignore any value sent
 * for it. Existing fields stay writable, so nothing changes until an admin
 * opts a field in.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pirep_fields') || Schema::hasColumn('pirep_fields', 'system_only')) {
            return;
        }

        Schema::table('pirep_fields', function (Blueprint $table): void {
            $table->boolean('system_only')->default(false)->after('pirep_source');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pirep_fields', 'system_only')) {
            Schema::table('pirep_fields', function (Blueprint $table): void {
                $table->dropColumn('system_only');
            });
        }
    }
};
