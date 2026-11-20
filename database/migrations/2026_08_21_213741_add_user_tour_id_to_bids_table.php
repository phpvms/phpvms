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
        if (Schema::hasColumn('bids', 'user_tour_id')) {
            return;
        }

        Schema::table('bids', function (Blueprint $table): void {
            $column = $table->string('user_tour_id', 36)->nullable()->after('aircraft_id')->index();

            // v7 `bids` carries the server default collation; the FK needs `user_tours`.`id`'s.
            if (($parent = $this->parentCollation('user_tours', 'id')) !== null) {
                $column->charset($parent[0])->collation($parent[1]);
            }

            $table->foreign('user_tour_id')->references('id')->on('user_tours')->cascadeOnDelete();
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
    private function parentCollation(string $table, string $column): ?array
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
