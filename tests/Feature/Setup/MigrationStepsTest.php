<?php

declare(strict_types=1);

use App\Features\Addons\Support\AddonRoots;
use App\Features\Addons\Support\ManifestParser;
use App\Features\Setup\Steps\RunDataMigrations;
use App\Features\Setup\Steps\RunMigrations;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\StepContext;
use App\Models\Addon;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Two recent migrations that each only create a table, so they can be undone
 * by dropping it and forgetting the row.
 */
const MIGRATION_STEP_TABLES = [
    '2026_10_31_000000_create_theme_settings_table' => 'theme_settings',
    '2026_11_02_225905_create_global_slots_table'   => 'global_slots',
];

/**
 * @param list<array{int, int, ?string}> $progress
 */
function migrationStepContext(array &$progress): StepContext
{
    return new StepContext(
        false,
        static function (string $line): void {},
        static function (int $done, int $total, ?string $item) use (&$progress): void {
            $progress[] = [$done, $total, $item];
        },
    );
}

function forgetMigrationStepTables(): void
{
    foreach (MIGRATION_STEP_TABLES as $migration => $table) {
        DB::table('migrations')->where('migration', $migration)->delete();
        Schema::dropIfExists($table);
    }
}

function migrationStepTempDir(): string
{
    $dir = sys_get_temp_dir().'/phpvms-migration-steps-'.uniqid('', true);
    mkdir($dir, 0777, true);
    phpvmsTempPaths([...phpvmsTempPaths(), $dir]);

    return $dir;
}

test('migrate reports progress per migration and ends with nothing pending', function (): void {
    forgetMigrationStepTables();

    $progress = [];
    app(RunMigrations::class)->run(migrationStepContext($progress));

    expect($progress)->toBe([
        [1, 2, '2026_10_31_000000_create_theme_settings_table'],
        [2, 2, '2026_11_02_225905_create_global_slots_table'],
    ])
        ->and(app(MigrationPaths::class)->pendingSchema())->toBe([]);
});

test('migrate after a partial run runs only the remaining migrations', function (): void {
    forgetMigrationStepTables();

    $started = [];
    Event::listen(MigrationStarted::class, function (MigrationStarted $event) use (&$started): void {
        $started[] = $event->name;
    });

    $progress = [];
    app(RunMigrations::class)->run(migrationStepContext($progress));

    expect($started)->toBe(array_keys(MIGRATION_STEP_TABLES))
        ->and(Schema::hasTable('theme_settings'))->toBeTrue()
        ->and(Schema::hasTable('global_slots'))->toBeTrue();
});

test('data paths contain only database/migrations_data', function (): void {
    expect(app(MigrationPaths::class)->data())->toBe([database_path('migrations_data')]);
});

test('data migrate reports progress per data migration', function (): void {
    $dir = migrationStepTempDir();
    $noop = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class() extends Migration\n{\n    public function up(): void {}\n};\n";
    file_put_contents($dir.'/2099_01_01_000000_first_fixture_data.php', $noop);
    file_put_contents($dir.'/2099_01_01_000001_second_fixture_data.php', $noop);

    $paths = new class($dir) extends MigrationPaths
    {
        public function __construct(private readonly string $dir) {}

        public function data(): array
        {
            return [$this->dir];
        }
    };
    app()->instance(MigrationPaths::class, $paths);

    $progress = [];
    app(RunDataMigrations::class)->run(migrationStepContext($progress));

    expect($progress)->toBe([
        [1, 2, '2099_01_01_000000_first_fixture_data'],
        [2, 2, '2099_01_01_000001_second_fixture_data'],
    ])
        ->and($paths->pendingData())->toBe([]);
});

test('a migration whose table exists but has no migrations row fails naming the migration', function (): void {
    $migration = '2026_08_11_010000_create_simbrief_attempts_table';
    $batch = DB::table('migrations')->where('migration', $migration)->value('batch');
    DB::table('migrations')->where('migration', $migration)->delete();

    try {
        $progress = [];
        expect(fn () => app(RunMigrations::class)->run(migrationStepContext($progress)))
            ->toThrow(RuntimeException::class, $migration);
    } finally {
        // The failed CREATE TABLE commits the test transaction, so the row must be put back by hand.
        DB::table('migrations')->insert(['migration' => $migration, 'batch' => $batch]);
    }
})->skip(
    fn (): bool => !in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true),
    'Exercises the MySQL "table exists" error code (1050).',
);

test("a bundled addon's migrations run when the addon boot cache is missing", function (): void {
    $source = migrationStepTempDir();
    mkdir($source.'/fixture-bundled/database/migrations', 0777, true);
    file_put_contents(
        $source.'/fixture-bundled/database/migrations/2099_01_01_000000_create_fixture_bundled_table.php',
        <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class() extends Migration
            {
                public function up(): void
                {
                    Schema::create('fixture_bundled_items', function (Blueprint $table): void {
                        $table->id();
                    });
                }
            };
            PHP,
    );
    config(['addons.paths.bundled' => $source, 'addons.bundled' => ['fixture-bundled']]);

    $progress = [];
    app(RunMigrations::class)->run(migrationStepContext($progress));

    expect(Schema::hasTable('fixture_bundled_items'))->toBeTrue()
        ->and($progress)->toBe([[1, 1, '2099_01_01_000000_create_fixture_bundled_table']]);
});

test('after a v7 run every bundled addon is enabled', function (): void {
    // A v7 database has no data-migration records.
    DB::table(config('data-migrations.table'))->delete();

    DB::table('addons')->insert([
        'name'        => 'Other',
        'registry_id' => 'acme/other',
        'type'        => 'module',
        'namespace'   => 'Modules\\AcmeOther',
        'path'        => '/nowhere/acme-other',
        'enabled'     => false,
        'bundled'     => false,
    ]);

    $progress = [];
    app(RunMigrations::class)->run(migrationStepContext($progress));
    app(RunDataMigrations::class)->run(migrationStepContext($progress));

    $parser = app(ManifestParser::class);
    foreach (AddonRoots::bundled() as $name) {
        $registryId = $parser->parse(AddonRoots::bundledSource().'/'.$name)?->registryId;

        expect(DB::table('addons')->where('registry_id', $registryId)->where('enabled', true)->where('bundled', true)->exists())
            ->toBeTrue("{$name} is not enabled");
    }

    expect((bool) DB::table('addons')->where('registry_id', 'acme/other')->value('enabled'))->toBeFalse();
});

test('schema paths include the migrations of an enabled addon', function (): void {
    $dir = migrationStepTempDir();
    mkdir($dir.'/database/migrations', 0777, true);
    Addon::factory()->create(['path' => $dir, 'enabled' => true]);

    expect(app(MigrationPaths::class)->schema())->toContain($dir.'/database/migrations');
});
