<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ACARS contract's `TelemetryPoint` carries average N1/N2 engine percent
 * across all engines as optional doubles, but no column exists for them, so
 * they are silently dropped today. This adds the 2 missing columns.
 *
 * Nullable with NO default, same reasoning as the other telemetry columns:
 * the Data Config page can switch a field off, and only a NULL (not a
 * `default(0)`) avoids a full-width write for an omitted reading.
 */
return new class() extends Migration
{
    /** Columns added here, all nullable and defaultless. */
    private const array ADDED = [
        'eng_n1_avg_pct',
        'eng_n2_avg_pct',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            if (!Schema::hasColumn('acars', 'eng_n1_avg_pct')) {
                $table->double('eng_n1_avg_pct')->nullable();
            }

            if (!Schema::hasColumn('acars', 'eng_n2_avg_pct')) {
                $table->double('eng_n2_avg_pct')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $existing = array_values(array_filter(
                self::ADDED,
                static fn (string $column): bool => Schema::hasColumn('acars', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
