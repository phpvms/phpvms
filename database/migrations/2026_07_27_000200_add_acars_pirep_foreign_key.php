<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Referential integrity between `acars` and `pireps`.
     *
     * `PirepService::delete()` has listed `acars` in its docblock as a child
     * table to remove since it was written, and never removed it. There was no
     * foreign key and no `deleting` observer either, so every install that has
     * ever hard-deleted a PIREP carries `acars` rows whose parent is gone.
     *
     * ⚠ THIS MIGRATION PERMANENTLY DELETES THOSE ORPHANED ROWS. ⚠
     *
     * It has to: the database rejects the constraint while they exist (MySQL
     * errno 1452, PostgreSQL SQLSTATE 23503). `down()` drops the constraint, and
     * that is all it does — the purged rows are not restored and cannot be. This
     * is stated again in the release notes.
     *
     * The rows were unreachable before this ran. Nothing joins `acars` to a
     * PIREP that does not exist, and `pireps` soft-deletes, so a soft-deleted
     * PIREP still has a row and its telemetry is *not* an orphan.
     *
     * Measured on a synthetic 2,000,000-row `acars` table with 1,000,000
     * orphans (MySQL 8): the count query under half a second, the chunked purge
     * 29 seconds, the constraint 9 seconds. An install with more orphans than
     * that will take proportionally longer.
     */
    private const int CHUNK = 10_000;

    public function up(): void
    {
        if (!Schema::hasTable('acars') || !Schema::hasTable('pireps')) {
            return;
        }

        if ($this->hasForeignKey()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Before the orphan sweep, not just before the constraint: comparing
        // `acars`.`pirep_id` against `pireps`.`id` across two collations is
        // itself an error (1267) on MySQL, so the anti-join below would fail
        // rather than the ALTER.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->alignPirepIdCollation();
        }

        $this->purgeOrphans();

        // SQLite has no ALTER TABLE ADD CONSTRAINT, and Laravel's SQLiteGrammar
        // compiles a foreign() against an existing table to nothing at all, so
        // the constraint would be silently absent rather than skipped. Skip it
        // explicitly so a reader can see that it is missing on purpose.
        //
        // Rebuilding `acars` to introduce it — the way `flight_subfleet` does —
        // is not worth it here: `acars` is the largest table in a mature
        // install, and this would be a great deal of migration machinery to
        // satisfy a platform phpVMS does not run in production. The cascade
        // guarantee rests on PirepService::delete() there, which is the path
        // real code takes.
        if ($driver === 'sqlite') {
            Log::info('acars: skipping the pireps foreign key — SQLite cannot add one to an existing table. Child rows are removed by PirepService::delete() instead.');

            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    /**
     * Drops the constraint. It does NOT restore the rows `up()` purged — those
     * are gone permanently.
     */
    public function down(): void
    {
        if (!Schema::hasTable('acars') || !$this->hasForeignKey()) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->dropForeign(['pirep_id']);
        });
    }

    /**
     * Whether `acars`.`pirep_id` already carries a foreign key, so a re-run
     * against an already-migrated database is a no-op.
     */
    private function hasForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('acars'))
            ->flatMap(fn (array $key): array => $key['columns'])
            ->contains('pirep_id');
    }

    /**
     * Delete `acars` rows whose parent PIREP no longer exists, in bounded
     * batches.
     *
     * Bounded because `acars` is the largest table in a mature install and a
     * single unbounded DELETE can hold locks for minutes. The page is selected
     * and then deleted by primary key rather than deleted through the join,
     * because a multi-table DELETE takes no LIMIT on MySQL and this shape works
     * identically on all three platforms.
     *
     * The count is logged before anything is removed, so an operator reading
     * their upgrade log can see what was destroyed and how much.
     */
    private function purgeOrphans(): void
    {
        $orphans = fn () => DB::table('acars')
            ->whereNotNull('acars.pirep_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('pireps')
                ->whereColumn('pireps.id', 'acars.pirep_id'));

        $total = $orphans()->count();

        if ($total === 0) {
            return;
        }

        Log::warning('acars: permanently deleting '.$total.' orphaned row(s) whose PIREP no longer exists. This cannot be undone.');

        $deleted = 0;

        while (true) {
            $ids = $orphans()->limit(self::CHUNK)->pluck('acars.id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += DB::table('acars')->whereIn('id', $ids)->delete();
        }

        Log::info('acars: purged '.$deleted.' orphaned row(s).');
    }

    /**
     * MySQL rejects a foreign key whose referencing and referenced columns
     * disagree on charset or collation, and errors on the anti-join before that.
     * `pireps`.`id` pins itself to `utf8mb4_unicode_ci` at table level, but an
     * upgraded install may have picked up the server default
     * (`utf8mb4_0900_ai_ci` on MySQL 8) for `acars`, so restate the child column
     * with whatever the parent actually uses.
     */
    private function alignPirepIdCollation(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $sql = 'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation
                from information_schema.COLUMNS
                where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?';

        $child = DB::selectOne($sql, [$connection->getDatabaseName(), $prefix.'acars', 'pirep_id']);
        $parent = DB::selectOne($sql, [$connection->getDatabaseName(), $prefix.'pireps', 'id']);

        if ($child === null || $parent === null || $child->collation === $parent->collation) {
            return;
        }

        // Identifiers, not values, so they cannot be bound; they come from
        // information_schema rather than from user input.
        DB::statement(sprintf(
            'alter table `%s` modify `pirep_id` varchar(36) character set %s collate %s not null',
            $prefix.'acars',
            $parent->charset,
            $parent->collation
        ));
    }
};
