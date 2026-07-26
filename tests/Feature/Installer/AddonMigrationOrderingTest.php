<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Services\Installer\MigrationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Create a fixture addon directory on disk (module.json, optionally a
 * database/migrations directory with a real migration), so
 * MigrationService::getMigrationPaths() has something to discover. Mirrors
 * makeSeedableAddon() in AddonSeedMarkerTest.
 */
function makeDiskAddon(string $base, string $dirName, array $options = []): string
{
    $path = $base.'/'.$dirName;
    File::ensureDirectoryExists($path);

    if ($options['module_json'] ?? true) {
        File::put($path.'/module.json', json_encode(['name' => $dirName, 'providers' => []]));
    }

    if ($options['migrations'] ?? false) {
        $migrationDir = $path.'/database/migrations';
        File::ensureDirectoryExists($migrationDir);

        $slug = substr(md5($path), 0, 8);
        $table = 'fixture_migration_'.$slug;

        // Filename (sans .php) is the migration's unique key in the migrations
        // table, so it must differ per fixture addon.
        File::put($migrationDir.sprintf('/2026_07_25_000001_create_fixture_table_%s.php', $slug), <<<PHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class() extends Migration
            {
                public function up(): void
                {
                    Schema::create('{$table}', function (Blueprint \$table): void {
                        \$table->id();
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('{$table}');
                }
            };
            PHP);
    }

    return $path;
}

/**
 * Create a fixture addon backed by a real DB row (App\Models\Addon), with a
 * matching directory + migration on disk so a `getMigrationPaths()` lookup
 * against AddonRegistry::enabled() actually resolves.
 */
function makeDbAddon(string $base, string $dirName, bool $enabled): Addon
{
    $path = makeDiskAddon($base, $dirName, ['migrations' => true]);

    return Addon::factory()->create(['path' => $path, 'enabled' => $enabled]);
}

beforeEach(function (): void {
    $this->addonBase = sys_get_temp_dir().'/phpvms-addon-migration-ordering-'.uniqid('', true);
    File::ensureDirectoryExists($this->addonBase);
    Config::set('addons.paths.base', $this->addonBase);

    // Bundled module rows seeded by the addons migration aren't relevant here
    // and would leak into the "DB is source of truth" assertions.
    Addon::query()->delete();
});

afterEach(function (): void {
    File::deleteDirectory($this->addonBase);
});

describe('pre-install (no addons table)', function (): void {
    beforeEach(function (): void {
        Schema::drop('addons');
    });

    it('includes a disk addon with a module.json and a migrations directory', function (): void {
        makeDiskAddon($this->addonBase, 'Demo', ['migrations' => true]);

        $paths = app(MigrationService::class)->getMigrationPaths();

        expect($paths)->toHaveKey('Demo')
            ->and($paths['Demo'])->toBe(realpath($this->addonBase).'/Demo/database/migrations');
    });

    it('excludes a disk directory with no module.json', function (): void {
        makeDiskAddon($this->addonBase, 'NoManifest', ['module_json' => false, 'migrations' => true]);

        $paths = app(MigrationService::class)->getMigrationPaths();

        expect($paths)->not->toHaveKey('NoManifest');
    });

    it('excludes a disk addon with no database/migrations subdirectory', function (): void {
        makeDiskAddon($this->addonBase, 'NoMigrations', ['migrations' => false]);

        $paths = app(MigrationService::class)->getMigrationPaths();

        expect($paths)->not->toHaveKey('NoMigrations');
    });

    it('includes a symlinked module directory (phpvms-acars case)', function (): void {
        $realParent = sys_get_temp_dir().'/phpvms-addon-migration-ordering-real-'.uniqid('', true);
        $real = makeDiskAddon($realParent, 'ActualAcars', ['migrations' => true]);
        symlink($real, $this->addonBase.'/Linked');

        $paths = app(MigrationService::class)->getMigrationPaths();

        expect($paths)->toHaveKey('Linked')
            ->and($paths['Linked'])->toBe(realpath($real).'/database/migrations');

        File::deleteDirectory($realParent);
        @unlink($this->addonBase.'/Linked');
    });

    it('applies the same disk-scan behavior for the data migrations directory', function (): void {
        $path = makeDiskAddon($this->addonBase, 'Demo', ['migrations' => false]);
        File::ensureDirectoryExists($path.'/database/migrations_data');
        File::put($path.'/database/migrations_data/2026_07_25_000001_seed_fixture.php', '<?php');

        $paths = app(MigrationService::class)->getMigrationPaths('migrations_data');

        expect($paths)->toHaveKey('Demo')
            ->and($paths['Demo'])->toBe(realpath($path).'/database/migrations_data');
    });
});

describe('post-install (addons table present)', function (): void {
    it('includes an enabled addon and excludes a disabled one, even though both are on disk', function (): void {
        $enabled = makeDbAddon($this->addonBase, 'Enabled', enabled: true);
        $disabled = makeDbAddon($this->addonBase, 'Disabled', enabled: false);

        $paths = app(MigrationService::class)->getMigrationPaths();

        expect($paths)->toHaveKey($enabled->getName())
            ->and($paths)->not->toHaveKey($disabled->getName());
    });

    it('applies the same enabled/disabled filtering for the data migrations directory', function (): void {
        $enabled = makeDbAddon($this->addonBase, 'Enabled', enabled: true);
        $disabled = makeDbAddon($this->addonBase, 'Disabled', enabled: false);

        File::ensureDirectoryExists($enabled->getPath().'/database/migrations_data');
        File::ensureDirectoryExists($disabled->getPath().'/database/migrations_data');

        $paths = app(MigrationService::class)->getMigrationPaths('migrations_data');

        expect($paths)->toHaveKey($enabled->getName())
            ->and($paths)->not->toHaveKey($disabled->getName());
    });
});

it('runs an addon migration in the same pass as core on a simulated fresh install', function (): void {
    makeDiskAddon($this->addonBase, 'Demo', ['migrations' => true]);

    Schema::drop('addons');

    $service = app(MigrationService::class);

    expect($service->migrationsAvailable())->not->toBeEmpty();

    $service->runAllMigrations();

    expect($service->migrationsAvailable())->toBe([]);
});
