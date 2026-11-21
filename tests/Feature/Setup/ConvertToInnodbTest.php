<?php

declare(strict_types=1);

use App\Features\Setup\Steps\ConvertToInnodb;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\DB;

function innodbEngineOf(string $table): ?string
{
    $row = DB::selectOne(
        'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );

    return $row?->engine;
}

/**
 * @param array<int, array{int, int, ?string}> $progress
 */
function innodbContext(array &$progress): StepContext
{
    return new StepContext(
        false,
        static function (string $line): void {},
        static function (int $done, int $total, ?string $item) use (&$progress): void {
            $progress[] = [$done, $total, $item];
        },
    );
}

function onMysqlFamily(): bool
{
    return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
}

afterEach(function (): void {
    if (!onMysqlFamily()) {
        return;
    }

    DB::connection()->setTablePrefix('');
    foreach (['t_myisam', 'pv_one', 'other_x'] as $table) {
        DB::statement("DROP TABLE IF EXISTS `$table`");
    }
});

test('converts a MyISAM table and reports it', function (): void {
    DB::statement('CREATE TABLE t_myisam (id INT) ENGINE=MyISAM');
    $progress = [];

    $step = new ConvertToInnodb();
    expect($step->applies(innodbContext($progress)))->toBeTrue();
    $step->run(innodbContext($progress));

    expect(innodbEngineOf('t_myisam'))->toBe('InnoDB')
        ->and(collect($progress)->pluck(2)->all())->toContain('t_myisam');
})->skip(fn (): bool => !onMysqlFamily(), 'MySQL/MariaDB only');

test("leaves tables without this site's prefix alone", function (): void {
    DB::statement('CREATE TABLE pv_one (id INT) ENGINE=MyISAM');
    DB::statement('CREATE TABLE other_x (id INT) ENGINE=MyISAM');
    DB::connection()->setTablePrefix('pv_');
    $progress = [];

    new ConvertToInnodb()->run(innodbContext($progress));

    expect(innodbEngineOf('pv_one'))->toBe('InnoDB')
        ->and(innodbEngineOf('other_x'))->toBe('MyISAM');
})->skip(fn (): bool => !onMysqlFamily(), 'MySQL/MariaDB only');

test('a second run converts nothing', function (): void {
    DB::statement('CREATE TABLE t_myisam (id INT) ENGINE=MyISAM');
    $progress = [];
    new ConvertToInnodb()->run(innodbContext($progress));

    $again = [];
    new ConvertToInnodb()->run(innodbContext($again));

    expect($again)->toBe([]);
})->skip(fn (): bool => !onMysqlFamily(), 'MySQL/MariaDB only');
