<?php

use App\Models\Flight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Refine flight duplicate detection.
     *
     * Phase 1: canonicalize route_code / route_leg storage. Pre-existing rows
     * may hold '' or '0'; collapse to NULL so the strict-duplicate key namespace
     * (5-tuple `(bundle_id, airline_id, flight_number, route_code, route_leg)`
     * filtered to `enabled = true AND owner_type IS NULL`) is deterministic.
     *
     * Phase 2: auto-disable pre-existing duplicate rows. The 2026_05_19_100002
     * migration backfilled every legacy flight to bundle "Default"; production
     * installations almost certainly have many enabled, non-owner rows that
     * share the new 5-tuple. For each cluster, the lowest `id` keeps enabled;
     * every other row is set to `enabled = false`. A Spatie activitylog entry
     * is written per disabled flight so admins can audit and reconcile.
     *
     * Phase 3: add the generated STORED column `_dup_key` whose value is NULL
     * when the row is disabled OR owner-typed, otherwise the concatenation of
     * the 5-tuple. UNIQUE index on this column enforces the namespace at the
     * DB level. Multi-NULL is unique-allowed across MySQL / MariaDB / PgSQL /
     * SQLite so disabled and owner-typed rows freely share other-key values.
     *
     * Driver compatibility: the generated-column expression differs between
     * SQLite (`||` operator) and MySQL/MariaDB/PostgreSQL (`CONCAT_WS`). The
     * migration switches on `DB::connection()->getDriverName()` and raises on
     * an unsupported driver.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported database driver for routeforge-duplicate-detection-refinement: '.$driver);
        }

        // Phase 1: canonicalize route_code / route_leg storage.
        DB::table('flights')->whereIn('route_code', ['', '0'])->update(['route_code' => null]);
        DB::table('flights')->whereIn('route_leg', ['0', 0])->update(['route_leg' => null]);

        // SQLite's type affinity allows '' in integer columns; other drivers reject it.
        if ($driver === 'sqlite') {
            DB::table('flights')->where('route_leg', '')->update(['route_leg' => null]);
        }

        // Phase 2: auto-disable pre-existing duplicates.
        //
        // Single window-function pass classifies every enabled, non-owner row:
        //   - PARTITION BY the 5-tuple → groups rows with the same dedup key
        //   - ROW_NUMBER() ORDER BY id → 1 is the kept row, 2..N are duplicates
        //   - FIRST_VALUE(id) ORDER BY id → kept id for the activity log
        // Filter where rn > 1 to get (duplicate_id, kept_id) pairs.
        //
        // Window functions are supported on every driver this codebase targets
        // (MySQL 8.0+, MariaDB 10.2+, PostgreSQL all versions, SQLite 3.25+).
        // The COALESCE-on-NULL in the PARTITION BY collapses NULL route_code /
        // route_leg into the same partition as other rows with NULL there,
        // matching the strict-duplicate key semantics.
        $rankedSubquery = DB::table('flights')
            ->select('id')
            ->selectRaw("FIRST_VALUE(id) OVER (PARTITION BY bundle_id, airline_id, flight_number, COALESCE(route_code, ''), COALESCE(route_leg, 0) ORDER BY id) as kept_id")
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY bundle_id, airline_id, flight_number, COALESCE(route_code, ''), COALESCE(route_leg, 0) ORDER BY id) as rn")
            ->where('enabled', true)
            ->whereNull('owner_type');

        $duplicates = DB::query()
            ->fromSub($rankedSubquery, 'ranked')
            ->where('rn', '>', 1)
            ->get(['id', 'kept_id']);

        if ($duplicates->isNotEmpty()) {
            $duplicateIds = $duplicates->pluck('id')->all();
            $keptByDup = $duplicates->keyBy('id')->map(fn ($row) => $row->kept_id)->all();

            // Mark them disabled in one statement before logging so a partial
            // run still leaves the constraint creatable.
            DB::table('flights')->whereIn('id', $duplicateIds)->update(['enabled' => false]);

            // Activity logging is globally disabled by AppServiceProvider and
            // re-enabled only per-request (admin panel middleware,
            // EnableActivityLogging middleware). Migrations run without a
            // request context so we enable it explicitly around the dedup
            // writes, then disable to leave the global state as we found it.
            // Activity-log writes are defensively wrapped — logger failures
            // don't block the schema migration.
            activity()->enableLogging();

            try {
                foreach (Flight::query()->whereIn('id', $duplicateIds)->cursor() as $flight) {
                    $keptId = $keptByDup[$flight->id] ?? null;
                    try {
                        activity()
                            ->performedOn($flight)
                            ->withProperties(['kept_flight_id' => $keptId])
                            ->log(sprintf('Auto-disabled by RouteForge dedup migration (duplicate of flight %s)', $keptId));
                    } catch (Throwable) {
                        // Per-row activity-log failures don't block the
                        // migration; admins can reconstruct from row state.
                    }
                }
            } finally {
                activity()->disableLogging();
            }
        }

        // Phase 3: add the generated `_dup_key` column with the driver-specific
        // expression and create the UNIQUE index.
        //
        // Storage class: STORED on MySQL/MariaDB/PostgreSQL (faster reads,
        // expression evaluated at write time). VIRTUAL on SQLite because
        // SQLite's ALTER TABLE ADD COLUMN rejects STORED generated columns
        // (per SQLite docs: "the generated column being added cannot be the
        // STORED type"). VIRTUAL is computed at read time but is still
        // indexable so the UNIQUE constraint works the same way.
        $expr = $this->dupKeyExpression($driver);
        $storageClass = $driver === 'sqlite' ? 'VIRTUAL' : 'STORED';

        // Blueprint::storedAs / virtualAs exist but their expression handling
        // is driver-sensitive (Laravel quotes / wraps CASE compositions in
        // unhelpful ways). Use raw ALTER for predictability.
        DB::statement(sprintf('ALTER TABLE flights ADD COLUMN _dup_key VARCHAR(255) GENERATED ALWAYS AS (%s) %s', $expr, $storageClass));

        // Schema::table for the index is portable; CREATE UNIQUE INDEX raw
        // would also work but the Blueprint version emits the right syntax
        // for each driver automatically.
        Schema::table('flights', function ($table): void {
            $table->unique('_dup_key', 'flights_dup_key_unique');
        });
    }

    public function down(): void
    {
        // Drop the unique index, then the generated column. Auto-disabled
        // duplicates are NOT re-enabled: restoring them is an admin decision
        // and reverting them en masse could re-enable rows the admin had
        // already disabled for legitimate reasons.
        Schema::table('flights', function ($table): void {
            $table->dropUnique('flights_dup_key_unique');
        });

        Schema::table('flights', function ($table): void {
            $table->dropColumn('_dup_key');
        });
    }

    /**
     * Driver-specific expression for the `_dup_key` generated column.
     *
     * NULL when the row is disabled OR owner-typed; otherwise the 5-tuple
     * concatenation joined by `|` with empty string for null route_code /
     * route_leg.
     *
     * The boolean comparison differs: MySQL/MariaDB store BOOLEAN as TINYINT
     * so `enabled = 1` matches; PostgreSQL uses true literals; SQLite stores
     * boolean as 0/1 and compares either way. We use `1` for all three int-
     * style drivers and `TRUE` for pgsql to keep the SQL idiomatic per driver.
     */
    private function dupKeyExpression(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => 'CASE WHEN enabled = 1 AND owner_type IS NULL '
                ."THEN CONCAT_WS('|', bundle_id, airline_id, flight_number, COALESCE(route_code, ''), COALESCE(route_leg, '')) "
                .'ELSE NULL END',
            // PostgreSQL marks CONCAT_WS as STABLE (not IMMUTABLE) because of
            // implicit type coercions, which disqualifies it from STORED
            // generated columns. The `||` operator over text is IMMUTABLE, so
            // we cast non-text columns explicitly and COALESCE nullable ones.
            'pgsql' => 'CASE WHEN enabled = TRUE AND owner_type IS NULL '
                ."THEN bundle_id::text || '|' || airline_id::text || '|' || flight_number::text || '|' || COALESCE(route_code::text, '') || '|' || COALESCE(route_leg::text, '') "
                .'ELSE NULL END',
            'sqlite' => 'CASE WHEN enabled = 1 AND owner_type IS NULL '
                ."THEN (bundle_id || '|' || airline_id || '|' || flight_number || '|' || COALESCE(route_code, '') || '|' || COALESCE(route_leg, '')) "
                .'ELSE NULL END',
            default => throw new RuntimeException('Unsupported driver: '.$driver),
        };
    }
};
