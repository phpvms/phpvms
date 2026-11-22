<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs v7 sites where a v8 string FK column's collation did not match its
 * parent key: the column was created, the FK was not, and a retry skipped it.
 *
 * ⚠ PERMANENTLY DELETES pirep child rows whose parent is gone, so the FK can be
 * added; orphaned bids and pirep events only lose the link.
 */
return new class() extends Migration
{
    private const int CHUNK = 10_000;

    /** child table, child column, child key, parent table, on delete, orphans: delete or null */
    private const array PAIRS = [
        ['pirep_positions', 'pirep_id', 'pirep_id', 'pireps', 'cascade', 'delete'],
        ['pirep_archive', 'pirep_id', 'pirep_id', 'pireps', 'cascade', 'delete'],
        // A nullable link: the event log and bid outlive a lost parent, so only the link is cleared.
        ['pirep_events', 'acars_id', 'id', 'acars', 'restrict', 'null'],
        ['bids', 'user_tour_id', 'id', 'user_tours', 'cascade', 'null'],
    ];

    public function up(): void
    {
        if (!in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::PAIRS as [$child, $column, $key, $parent, $onDelete, $orphans]) {
            $childColumn = $this->column($child, $column);
            $parentColumn = $this->column($parent, 'id');

            if ($childColumn === null || $parentColumn === null) {
                continue;
            }

            if ($childColumn->collation !== $parentColumn->collation) {
                // Interpolated below as identifiers, which cannot be bound.
                foreach ([$child, $column, $parentColumn->charset, $parentColumn->collation] as $identifier) {
                    throw_unless(preg_match('/^\w+$/', (string) $identifier), RuntimeException::class, "Unsafe identifier: {$identifier}");
                }

                // MODIFY restates the column only; the primary key on pirep_positions survives.
                DB::statement(sprintf(
                    'alter table `%s` modify `%s` varchar(36) character set %s collate %s %s',
                    Schema::getConnection()->getTablePrefix().$child,
                    $column,
                    $parentColumn->charset,
                    $parentColumn->collation,
                    $childColumn->nullable === 'YES' ? 'null' : 'not null'
                ));
            }

            if ($this->hasForeignKey($child, $column, $parent)) {
                continue;
            }

            $this->purgeOrphans($child, $column, $key, $parent, $orphans === 'null');

            Schema::table($child, function (Blueprint $table) use ($column, $parent, $onDelete): void {
                $table->foreign($column)->references('id')->on($parent)->onDelete($onDelete);
            });
        }
    }

    public function down(): void {}

    private function column(string $table, string $column): ?object
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        $connection = Schema::getConnection();

        return DB::selectOne(
            'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation, IS_NULLABLE as nullable
            from information_schema.COLUMNS
            where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$connection->getDatabaseName(), $connection->getTablePrefix().$table, $column]
        );
    }

    private function hasForeignKey(string $child, string $column, string $parent): bool
    {
        $prefixed = Schema::getConnection()->getTablePrefix().$parent;

        return collect(Schema::getForeignKeys($child))->contains(
            fn (array $fk): bool => in_array($column, $fk['columns'], true)
                && in_array($fk['foreign_table'], [$parent, $prefixed], true)
        );
    }

    /** Batched by key, as a multi-table DELETE takes no LIMIT on MySQL. */
    private function purgeOrphans(string $child, string $column, string $key, string $parent, bool $nullify): void
    {
        $orphans = fn () => DB::table($child)
            ->whereNotNull("{$child}.{$column}")
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from($parent)
                ->whereColumn("{$parent}.id", "{$child}.{$column}"));

        $total = $orphans()->count();

        if ($total === 0) {
            return;
        }

        if ($nullify) {
            Log::warning("{$child}: clearing {$column} on {$total} row(s) whose {$parent} row no longer exists.");
            $orphans()->update([$column => null]);

            return;
        }

        Log::warning("{$child}: permanently deleting {$total} row(s) whose {$parent} row no longer exists.");

        while (($ids = $orphans()->limit(self::CHUNK)->pluck("{$child}.{$key}"))->isNotEmpty()) {
            DB::table($child)->whereIn($key, $ids)->delete();
        }
    }
};
