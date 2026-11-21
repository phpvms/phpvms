<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups the N bids a tour creates, so cancelling or expiring one is a single
 * statement over one index. Existing bids get null and behave exactly as before.
 *
 * Grouped by the run and not by `bundle_id` on purpose: a pilot's second attempt
 * at a tour must not be conflated with their first.
 */
return new class() extends Migration
{
    public function up(): void
    {
        // Column, index and foreign key are separate statements on MySQL; add whichever a cut-short run left out.
        $hasColumn = Schema::hasColumn('bids', 'user_tour_id');
        $hasIndex = $hasColumn && Schema::hasIndex('bids', ['user_tour_id']);
        $hasForeign = $hasColumn && collect(Schema::getForeignKeys('bids'))->contains(fn (array $fk): bool => $fk['columns'] === ['user_tour_id']);

        // v7 `bids` carries the server default collation; the FK needs `user_tours`.`id`'s.
        $parent = $this->columnCollation('user_tours', 'id');

        // A column an earlier build added in the wrong collation would fail the FK on every retry.
        if ($hasColumn && !$hasForeign && $parent !== null && $this->columnCollation('bids', 'user_tour_id') !== $parent) {
            Schema::table('bids', function (Blueprint $table) use ($parent): void {
                $table->string('user_tour_id', 36)->nullable()->charset($parent[0])->collation($parent[1])->change();
            });
        }

        Schema::table('bids', function (Blueprint $table) use ($hasColumn, $hasIndex, $hasForeign, $parent): void {
            if (!$hasColumn) {
                $column = $table->string('user_tour_id', 36)->nullable()->after('aircraft_id');

                if ($parent !== null) {
                    $column->charset($parent[0])->collation($parent[1]);
                }
            }

            if (!$hasIndex) {
                $table->index('user_tour_id');
            }

            if (!$hasForeign) {
                $table->foreign('user_tour_id')->references('id')->on('user_tours')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table): void {
            $table->dropForeign(['user_tour_id']);
            $table->dropColumn('user_tour_id');
        });
    }

    /** @return array{string, string}|null */
    private function columnCollation(string $table, string $column): ?array
    {
        $connection = Schema::getConnection();

        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        $row = $connection->selectOne(
            'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation
            from information_schema.COLUMNS
            where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$connection->getDatabaseName(), $connection->getTablePrefix().$table, $column]
        );

        return $row?->collation !== null ? [$row->charset, $row->collation] : null;
    }
};
