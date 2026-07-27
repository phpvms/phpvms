<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the per-sample phase columns able to hold what the ACARS contract
 * actually sends, and drops the dead `acars.source`.
 *
 * `phase` is an OPEN string vocabulary on the wire (contract.proto "Flight
 * phase"): a phase added upstream must store without a phpVMS release. Three
 * characters is exactly today's `PirepPhase` codes and nothing more, so the
 * first four-character code would truncate or error. Both columns widen to 20
 * and take the client's string verbatim — no enum round-trip.
 *
 * `acars` gains `phase` outright (after `type`, where the row's classification
 * belongs). `status`, the char(3) that was carrying it, is kept and still
 * populated so anything reading the old column keeps working — widened for the
 * same reason.
 *
 * `pireps.status` deliberately keeps its PirepPhase cast: it is a lifecycle
 * column phpVMS itself owns and sets (PirepService::file forces ARRIVED), not
 * a per-sample reading passed through from a client.
 *
 * `acars.source` goes: nothing in core has ever read or written it, and the
 * PIREP row already records provenance via `pireps.source`.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acars')) {
            if (!Schema::hasColumn('acars', 'phase')) {
                Schema::table('acars', function (Blueprint $table): void {
                    $table->string('phase', 20)->nullable()->after('type');
                });

                // Backfill from the column that used to hold it, so existing
                // flight paths keep their per-point phase.
                DB::table('acars')->whereNotNull('status')->update(['phase' => DB::raw('status')]);
            }

            Schema::table('acars', function (Blueprint $table): void {
                $table->string('status', 20)->nullable()->default('SCH')->change();
            });

            if (Schema::hasColumn('acars', 'source')) {
                Schema::table('acars', function (Blueprint $table): void {
                    $table->dropColumn('source');
                });
            }
        }

        if (Schema::hasTable('pirep_positions')) {
            Schema::table('pirep_positions', function (Blueprint $table): void {
                $table->string('phase', 20)->default('SCH')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acars')) {
            Schema::table('acars', function (Blueprint $table): void {
                if (Schema::hasColumn('acars', 'phase')) {
                    $table->dropColumn('phase');
                }

                if (!Schema::hasColumn('acars', 'source')) {
                    $table->string('source', 5)->nullable();
                }
            });

            Schema::table('acars', function (Blueprint $table): void {
                $table->char('status', 3)->default('SCH')->change();
            });
        }

        if (Schema::hasTable('pirep_positions')) {
            Schema::table('pirep_positions', function (Blueprint $table): void {
                $table->string('phase', 3)->default('SCH')->change();
            });
        }
    }
};
