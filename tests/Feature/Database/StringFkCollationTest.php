<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * DDL commits implicitly on MySQL, so RefreshDatabase cannot roll these back:
 * every test restores `pireps`.`id` and its children by hand.
 */

function stringFkOnMysqlFamily(): bool
{
    return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
}

function stringFkColumn(string $table, string $column): object
{
    return DB::selectOne(
        'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation, IS_NULLABLE as nullable, COLUMN_TYPE as type
        from information_schema.COLUMNS where TABLE_SCHEMA = DATABASE() and TABLE_NAME = ? and COLUMN_NAME = ?',
        [$table, $column]
    );
}

function stringFkModify(string $table, string $column, object $as): void
{
    DB::statement(sprintf(
        'alter table `%s` modify `%s` %s character set %s collate %s %s',
        $table,
        $column,
        $as->type,
        $as->charset,
        $as->collation,
        $as->nullable === 'YES' ? 'null' : 'not null'
    ));
}

/** @return array<int, array{table: string, fk: array<string, mixed>, column: object}> */
function stringFkChildrenOfPireps(): array
{
    $tables = collect(DB::select(
        'select distinct TABLE_NAME as name from information_schema.KEY_COLUMN_USAGE
        where TABLE_SCHEMA = DATABASE() and REFERENCED_TABLE_NAME = ?',
        ['pireps']
    ))->pluck('name');

    return $tables->flatMap(fn (string $table) => collect(Schema::getForeignKeys($table))
        ->filter(fn (array $fk): bool => $fk['foreign_table'] === 'pireps')
        ->map(fn (array $fk): array => [
            'table'  => $table,
            'fk'     => $fk,
            'column' => stringFkColumn($table, $fk['columns'][0]),
        ]))->values()->all();
}

function stringFkDropChildrenOfPireps(): void
{
    foreach (stringFkChildrenOfPireps() as $child) {
        Schema::table($child['table'], fn (Blueprint $table) => $table->dropForeign($child['fk']['name']));
    }
}

/** Puts `pireps`.`id`, each child column and each FK back the way they were. */
function stringFkRestore(object $pirepsId, array $children): void
{
    stringFkDropChildrenOfPireps();
    stringFkModify('pireps', 'id', $pirepsId);

    foreach ($children as $child) {
        if (!Schema::hasTable($child['table'])) {
            continue;
        }

        stringFkModify($child['table'], $child['fk']['columns'][0], $child['column']);
        Schema::table($child['table'], fn (Blueprint $table) => $table->foreign($child['fk']['columns'], $child['fk']['name'])
            ->references('id')->on('pireps')->onDelete($child['fk']['on_delete']));
    }
}

function stringFkTurkishPirepsId(object $pirepsId): void
{
    stringFkDropChildrenOfPireps();
    stringFkModify('pireps', 'id', (object) [...(array) $pirepsId, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_turkish_ci']);
}

/** Table default collation plus every column's, to prove a restore is complete. */
function stringFkTableShape(string $table): array
{
    return [
        DB::selectOne('select TABLE_COLLATION as c from information_schema.TABLES where TABLE_SCHEMA = DATABASE() and TABLE_NAME = ?', [$table])->c,
        collect(DB::select('select COLUMN_NAME as n, COLLATION_NAME as c from information_schema.COLUMNS where TABLE_SCHEMA = DATABASE() and TABLE_NAME = ?', [$table]))
            ->pluck('c', 'n')->sortKeys()->all(),
    ];
}

function stringFkRecreatePositions(): void
{
    Schema::dropIfExists('pirep_positions');
    DB::table('migrations')->where('migration', '2026_07_27_000000_create_pirep_positions_table')->delete();

    Artisan::call('migrate', [
        '--path'  => 'database/migrations/2026_07_27_000000_create_pirep_positions_table.php',
        '--force' => true,
    ]);
}

function stringFkRepair(): object
{
    return require base_path('database/migrations_data/2026_11_18_100000_align_string_fk_collations.php');
}

function stringFkHasPirepsKey(string $table): bool
{
    return collect(Schema::getForeignKeys($table))
        ->contains(fn (array $fk): bool => $fk['foreign_table'] === 'pireps' && $fk['columns'] === ['pirep_id']);
}

test('pirep_positions is created with the collation of pireps.id', function (): void {
    $pirepsId = stringFkColumn('pireps', 'id');
    $children = stringFkChildrenOfPireps();
    $shape = stringFkTableShape('pirep_positions');

    try {
        stringFkTurkishPirepsId($pirepsId);
        stringFkRecreatePositions();

        expect(Schema::hasTable('pirep_positions'))->toBeTrue()
            ->and(stringFkColumn('pirep_positions', 'pirep_id')->collation)->toBe('utf8mb4_turkish_ci')
            ->and(stringFkHasPirepsKey('pirep_positions'))->toBeTrue()
            ->and(stringFkTableShape('pirep_positions')[0])->not->toBe('utf8mb4_turkish_ci');
    } finally {
        stringFkRestore($pirepsId, $children);
        // The turkish-era table took that collation on every column; rebuild it whole.
        stringFkRecreatePositions();
    }

    expect(stringFkTableShape('pirep_positions'))->toBe($shape);
})->skip(fn (): bool => !stringFkOnMysqlFamily(), 'MySQL/MariaDB only');

test('the repair migration restates a mismatched child column and adds the missing FK', function (): void {
    $pirepsId = stringFkColumn('pireps', 'id');
    $children = stringFkChildrenOfPireps();

    try {
        stringFkTurkishPirepsId($pirepsId);
        DB::table('pirep_positions')->insert(['pirep_id' => 'orphan-pirep', 'user_id' => 1]);

        stringFkRepair()->up();

        $primary = collect(Schema::getIndexes('pirep_positions'))->firstWhere('primary', true);

        expect(stringFkColumn('pirep_positions', 'pirep_id')->collation)->toBe('utf8mb4_turkish_ci')
            ->and(DB::table('pirep_positions')->where('pirep_id', 'orphan-pirep')->exists())->toBeFalse()
            ->and(stringFkHasPirepsKey('pirep_positions'))->toBeTrue()
            ->and($primary['columns'] ?? null)->toBe(['pirep_id']);
    } finally {
        DB::table('pirep_positions')->where('pirep_id', 'orphan-pirep')->delete();
        stringFkRestore($pirepsId, $children);
    }
})->skip(fn (): bool => !stringFkOnMysqlFamily(), 'MySQL/MariaDB only');

test('the repair migration is a no-op when collations already match', function (): void {
    $count = fn (): int => collect(['pirep_positions', 'pirep_archive', 'pirep_events'])
        ->sum(fn (string $table): int => count(Schema::getForeignKeys($table)));

    $before = $count();

    stringFkRepair()->up();

    expect($count())->toBe($before);
})->skip(fn (): bool => !stringFkOnMysqlFamily(), 'MySQL/MariaDB only');

test('the repair migration aligns bids.user_tour_id and clears a dangling tour link', function (): void {
    $original = stringFkColumn('bids', 'user_tour_id');
    $parent = stringFkColumn('user_tours', 'id');
    $shape = stringFkTableShape('bids');
    $dropKey = function (): void {
        if (collect(Schema::getForeignKeys('bids'))->contains('name', 'bids_user_tour_id_foreign')) {
            Schema::table('bids', fn (Blueprint $table) => $table->dropForeign('bids_user_tour_id_foreign'));
        }
    };

    try {
        $dropKey();
        stringFkModify('bids', 'user_tour_id', (object) [...(array) $original, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_turkish_ci']);

        // The bid's own user/flight keys are beside the point here.
        Schema::withoutForeignKeyConstraints(fn () => DB::table('bids')->insert([
            'user_id' => 424242, 'flight_id' => 'stringfk-flight', 'user_tour_id' => 'gone-tour',
        ]));

        stringFkRepair()->up();

        $hasKey = collect(Schema::getForeignKeys('bids'))
            ->contains(fn (array $fk): bool => $fk['foreign_table'] === 'user_tours' && $fk['columns'] === ['user_tour_id']);

        expect(stringFkColumn('bids', 'user_tour_id')->collation)->toBe($parent->collation)
            ->and(DB::table('bids')->where('flight_id', 'stringfk-flight')->sole()->user_tour_id)->toBeNull()
            ->and($hasKey)->toBeTrue();
    } finally {
        DB::table('bids')->where('flight_id', 'stringfk-flight')->delete();
        $dropKey();
        stringFkModify('bids', 'user_tour_id', $original);
        Schema::table('bids', fn (Blueprint $table) => $table->foreign('user_tour_id')
            ->references('id')->on('user_tours')->cascadeOnDelete());
    }

    expect(stringFkTableShape('bids'))->toBe($shape);
})->skip(fn (): bool => !stringFkOnMysqlFamily(), 'MySQL/MariaDB only');
