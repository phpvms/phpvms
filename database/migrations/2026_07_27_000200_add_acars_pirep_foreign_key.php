<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * ⚠ PERMANENTLY DELETES ORPHANED `acars` ROWS. `down()` drops the constraint
     * and does not bring them back.
     *
     * PirepService::delete() has claimed to remove `acars` since it was written and
     * never did, so most installs carry rows whose PIREP is gone, and the database
     * rejects the constraint while they exist.
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

        // Before the sweep, not just the constraint: the anti-join across two
        // collations is itself error 1267 on MySQL.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->alignPirepIdCollation();
        }

        $this->purgeOrphans();

        // SQLite has no ALTER TABLE ADD CONSTRAINT, and Laravel compiles foreign()
        // against an existing table to nothing, so skip it explicitly. Cleanup there
        // rests on PirepService::delete().
        if ($driver === 'sqlite') {
            Log::info('acars: skipping the pireps foreign key, SQLite cannot add one to an existing table.');

            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    /** Drops the constraint. Does NOT restore the rows up() purged. */
    public function down(): void
    {
        if (!Schema::hasTable('acars') || !$this->hasForeignKey()) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->dropForeign(['pirep_id']);
        });
    }

    /** So a re-run against an already-migrated database is a no-op. */
    private function hasForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('acars'))
            ->flatMap(fn (array $key): array => $key['columns'])
            ->contains('pirep_id');
    }

    /**
     * Batched because `acars` is the largest table in a mature install and an
     * unbounded DELETE can hold locks for minutes. Selected then deleted by primary
     * key, since a multi-table DELETE takes no LIMIT on MySQL.
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
     * `pireps`.`id` pins utf8mb4_unicode_ci, but an upgraded install may have picked
     * up the server default for `acars`. Restate the child to match the parent.
     */
    private function alignPirepIdCollation(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $sql = 'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation, IS_NULLABLE as nullable
                from information_schema.COLUMNS
                where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?';

        $child = DB::selectOne($sql, [$connection->getDatabaseName(), $prefix.'acars', 'pirep_id']);
        $parent = DB::selectOne($sql, [$connection->getDatabaseName(), $prefix.'pireps', 'id']);

        if ($child === null || $parent === null || $child->collation === $parent->collation) {
            return;
        }

        // Identifiers, not values, so they cannot be bound. From information_schema.
        DB::statement(sprintf(
            'alter table `%s` modify `pirep_id` varchar(36) character set %s collate %s %s',
            $prefix.'acars',
            $parent->charset,
            $parent->collation,
            $child->nullable === 'YES' ? 'null' : 'not null'
        ));
    }
};
