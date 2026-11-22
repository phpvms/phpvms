<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MySQL cannot roll back DDL, so a migration cut short between statements is replayed in part.
 * Each test leaves the schema as the full migration builds it.
 */

function rerunMigration(string $file): void
{
    (require database_path('migrations/'.$file.'.php'))->up();
}

function hasForeignOn(string $table, string $column): bool
{
    return collect(Schema::getForeignKeys($table))->contains(fn (array $fk): bool => $fk['columns'] === [$column]);
}

test('a create cut short before its foreign key is finished on retry', function (): void {
    Schema::drop('pirep_archive');
    Schema::create('pirep_archive', function (Blueprint $table): void {
        $table->string('pirep_id', 36)->primary();
    });

    rerunMigration('2026_08_05_002358_create_pirep_archive_table');

    expect(hasForeignOn('pirep_archive', 'pirep_id'))->toBeTrue()
        ->and(Schema::hasColumn('pirep_archive', 'navlog'))->toBeTrue();
});

test('a bid column cut short before its index and foreign key gets both on retry', function (): void {
    Schema::table('bids', function (Blueprint $table): void {
        $table->dropForeign(['user_tour_id']);
        $table->dropIndex(['user_tour_id']);
    });

    rerunMigration('2026_08_21_213741_add_user_tour_id_to_bids_table');

    expect(Schema::hasIndex('bids', ['user_tour_id']))->toBeTrue()
        ->and(hasForeignOn('bids', 'user_tour_id'))->toBeTrue();
});

test('the simbrief migration cut short after its first column adds the second on retry', function (): void {
    Schema::table('simbrief', function (Blueprint $table): void {
        $table->dropColumn('ofp_json_path');
    });

    rerunMigration('2025_07_26_170847_update_simbrief_structure');

    expect(Schema::hasColumn('simbrief', 'ofp_json_path'))->toBeTrue();
});

test('a bid column left in the wrong collation is aligned before its foreign key on retry', function (): void {
    if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Column collations are MySQL and MariaDB only.');
    }

    Schema::table('bids', function (Blueprint $table): void {
        $table->dropForeign(['user_tour_id']);
    });
    DB::statement('alter table bids modify user_tour_id varchar(36) character set utf8mb4 collate utf8mb4_turkish_ci null');

    rerunMigration('2026_08_21_213741_add_user_tour_id_to_bids_table');

    $collation = static fn (string $table, string $column): ?string => DB::selectOne(
        'select COLLATION_NAME as c from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
        [DB::getDatabaseName(), $table, $column],
    )?->c;

    expect(hasForeignOn('bids', 'user_tour_id'))->toBeTrue()
        ->and($collation('bids', 'user_tour_id'))->toBe($collation('user_tours', 'id'));
});
