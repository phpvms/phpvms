<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ACARS contract's `TelemetryPoint` grew from 13 stored fields to 28
 * (contract.proto): magnetic heading, attitude, and the aircraft's switch/light
 * state. This adds the 15 missing columns.
 *
 * Every one is nullable with NO default, because the ACARS plugin's Data Config
 * page lets an admin switch individual fields off. That only saves space if the
 * omitted column is NULL: InnoDB stores a fixed-width numeric at full width
 * whatever the value (a `0.0` double costs the same 8 bytes as a real reading),
 * and only elides the bytes entirely when the value is NULL. A `default(0)`
 * would silently turn every switched-off field back into a full-width write.
 *
 * For the same reason this drops the existing `default(0)` on `altitude_agl`,
 * `altitude_msl` and `vs` — they are already nullable, but the default meant an
 * omitted field stored 0 rather than NULL, so switching them off saved nothing.
 * `lat`/`lon` keep theirs: they are always written.
 *
 * Telemetry is the largest table in a phpVMS install, so the added width is
 * deliberate: ~113 bytes/row when every field is on.
 */
return new class() extends Migration
{
    /** Columns added here, all nullable and defaultless. */
    private const array ADDED = [
        'heading_mag',
        'pitch',
        'bank',
        'on_ground',
        'gear_up',
        'g_force',
        'throttle_pct',
        'flaps',
        'beacon_lights',
        'nav_lights',
        'strobe_lights',
        'landing_lights',
        'logo_lights',
        'taxi_lights',
        'wing_lights',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            // Magnetic heading, alongside the existing true `heading`.
            if (!Schema::hasColumn('acars', 'heading_mag')) {
                $table->unsignedSmallInteger('heading_mag')->nullable();
            }

            // Attitude and airframe state.
            if (!Schema::hasColumn('acars', 'pitch')) {
                $table->double('pitch')->nullable();
            }

            if (!Schema::hasColumn('acars', 'bank')) {
                $table->double('bank')->nullable();
            }

            if (!Schema::hasColumn('acars', 'on_ground')) {
                $table->boolean('on_ground')->nullable();
            }

            if (!Schema::hasColumn('acars', 'gear_up')) {
                $table->boolean('gear_up')->nullable();
            }

            if (!Schema::hasColumn('acars', 'g_force')) {
                $table->double('g_force')->nullable();
            }

            if (!Schema::hasColumn('acars', 'throttle_pct')) {
                $table->double('throttle_pct')->nullable();
            }

            // Notch or degrees, depending on the airframe; signed for negative
            // (reflex) settings.
            if (!Schema::hasColumn('acars', 'flaps')) {
                $table->smallInteger('flaps')->nullable();
            }

            // Exterior lights. Separate booleans rather than a bitmask: the
            // Data Config page toggles them individually, and a bitmask cannot
            // distinguish "off" from "not recorded".
            foreach (['beacon', 'nav', 'strobe', 'landing', 'logo', 'taxi', 'wing'] as $light) {
                if (!Schema::hasColumn('acars', $light.'_lights')) {
                    $table->boolean($light.'_lights')->nullable();
                }
            }
        });

        // Separate pass: `change()` on existing columns can't share a Blueprint
        // with adds on some drivers.
        Schema::table('acars', function (Blueprint $table): void {
            $table->decimal('altitude_agl')->nullable()->default(null)->change();
            $table->decimal('altitude_msl')->nullable()->default(null)->change();
            $table->double('vs')->nullable()->default(null)->change();
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

        Schema::table('acars', function (Blueprint $table): void {
            $table->decimal('altitude_agl')->nullable()->default(0)->change();
            $table->decimal('altitude_msl')->nullable()->default(0)->change();
            $table->double('vs')->nullable()->default(0)->change();
        });
    }
};
