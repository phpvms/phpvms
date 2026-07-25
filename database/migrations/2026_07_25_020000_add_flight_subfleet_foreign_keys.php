<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Referential integrity for the `flight_subfleet` pivot, which was created
     * without any. Both models soft-delete, so the pivot survived normal
     * deletes by design, but a force-delete of either side left rows pointing
     * at nothing for Flight::accessibleSubfleetsFor() to resolve.
     *
     * Cascade on both sides, matching `bundle_subfleet`: a pin is a property of
     * the pair, so it has no meaning once either end is really gone.
     */
    public function up(): void
    {
        if (!Schema::hasTable('flight_subfleet')) {
            return;
        }

        $constrained = $this->constrainedColumns();

        if (in_array('flight_id', $constrained, true) && in_array('subfleet_id', $constrained, true)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Before the orphan sweep, not just before the constraint: comparing
        // `flight_id` against `flights`.`id` across two collations is itself an
        // error (1267) on MySQL.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->alignFlightIdCollation();
        }

        // Deliberate cleanup: these rows already referenced a flight or subfleet
        // that no longer exists, so they were unreachable before this migration
        // and would only block the constraint now.
        $swept = DB::table('flight_subfleet')
            ->whereNotIn('flight_id', fn ($query) => $query->select('id')->from('flights'))
            ->orWhereNotIn('subfleet_id', fn ($query) => $query->select('id')->from('subfleets'))
            ->delete();

        // Deleting rows out from under an operator without a trace is unkind,
        // even when the rows were already unreachable.
        if ($swept > 0) {
            Log::info('flight_subfleet: swept '.$swept.' orphaned pivot rows before adding foreign keys.');
        }

        // SQLite has no ADD CONSTRAINT, and Laravel's grammar silently drops a
        // foreign() issued against an existing table, so the constraint can only
        // be introduced by rebuilding the table.
        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(withForeignKeys: true);

            return;
        }

        Schema::table('flight_subfleet', function (Blueprint $table) use ($constrained): void {
            if (!in_array('flight_id', $constrained, true)) {
                $table->foreign('flight_id')->references('id')->on('flights')->cascadeOnDelete();
            }

            if (!in_array('subfleet_id', $constrained, true)) {
                $table->foreign('subfleet_id')->references('id')->on('subfleets')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('flight_subfleet')) {
            return;
        }

        $constrained = $this->constrainedColumns();

        if ($constrained === []) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(withForeignKeys: false);

            return;
        }

        Schema::table('flight_subfleet', function (Blueprint $table) use ($constrained): void {
            if (in_array('flight_id', $constrained, true)) {
                $table->dropForeign(['flight_id']);
            }

            if (in_array('subfleet_id', $constrained, true)) {
                $table->dropForeign(['subfleet_id']);
            }
        });
    }

    /**
     * Columns of `flight_subfleet` that already carry a foreign key, so a
     * re-run against an already-migrated database is a no-op.
     *
     * @return list<string>
     */
    private function constrainedColumns(): array
    {
        return collect(Schema::getForeignKeys('flight_subfleet'))
            ->flatMap(fn (array $key) => $key['columns'])
            ->values()
            ->all();
    }

    /**
     * MySQL rejects a foreign key whose referencing and referenced columns
     * disagree on collation (error 3780). `flights.id` pins itself to
     * `utf8mb4_unicode_ci` at table level, but an upgraded install may have
     * picked up the server default (`utf8mb4_0900_ai_ci` on MySQL 8) for the
     * pivot, so restate the pivot column with whatever `flights.id` actually
     * uses before constraining it.
     */
    private function alignFlightIdCollation(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $sql = 'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation
                from information_schema.COLUMNS
                where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?';

        $pivot = DB::selectOne($sql, [$connection->getDatabaseName(), $prefix.'flight_subfleet', 'flight_id']);
        $flights = DB::selectOne($sql, [$connection->getDatabaseName(), $prefix.'flights', 'id']);

        if ($pivot === null || $flights === null || $pivot->collation === $flights->collation) {
            return;
        }

        // Identifiers, not values, so they cannot be bound; they come from
        // information_schema rather than user input.
        DB::statement(sprintf(
            'alter table `%s` modify `flight_id` varchar(36) character set %s collate %s not null',
            $prefix.'flight_subfleet',
            $flights->charset,
            $flights->collation
        ));
    }

    /**
     * SQLite's supported way to change a table's constraints: build the
     * replacement, copy the rows, swap it in.
     */
    private function rebuildSqliteTable(bool $withForeignKeys): void
    {
        // Laravel's SQLiteGrammar reports $transactions = false, so the Migrator
        // does not wrap this in one for us and the drop/rename below would tear
        // the pivot in half if the process died between them. SQLite does
        // support transactional DDL, so ask for it explicitly.
        DB::transaction(function () use ($withForeignKeys): void {
            // A run killed before the swap leaves the scratch table behind, and
            // Schema::create would then fail on every retry.
            Schema::dropIfExists('flight_subfleet_rebuild');

            Schema::create('flight_subfleet_rebuild', function (Blueprint $table) use ($withForeignKeys): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('subfleet_id');
                $table->string('flight_id', 36);

                if ($withForeignKeys) {
                    $table->foreign('flight_id')->references('id')->on('flights')->cascadeOnDelete();
                    $table->foreign('subfleet_id')->references('id')->on('subfleets')->cascadeOnDelete();
                }
            });

            DB::table('flight_subfleet_rebuild')->insertUsing(
                ['id', 'subfleet_id', 'flight_id'],
                DB::table('flight_subfleet')->select('id', 'subfleet_id', 'flight_id')
            );

            Schema::drop('flight_subfleet');
            Schema::rename('flight_subfleet_rebuild', 'flight_subfleet');

            // Index names are database-global in SQLite and a RENAME does not
            // rewrite them, so the originals can only be recreated under their
            // own names once the table that held them is gone.
            Schema::table('flight_subfleet', function (Blueprint $table): void {
                $table->index(['flight_id', 'subfleet_id']);
                $table->index(['subfleet_id', 'flight_id']);
            });
        });
    }
};
