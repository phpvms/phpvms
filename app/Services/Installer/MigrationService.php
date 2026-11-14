<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use App\Features\Addons\AddonRegistry;
use App\Features\Addons\Models\AddonManifest;
use App\Features\Addons\Support\AddonRoots;
use App\Features\Addons\Support\ManifestParser;
use App\Models\Addon;
use Closure;
use Exception;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MigrationService extends Service
{
    public function __construct(
        private readonly AddonRegistry $addonRegistry,
    ) {}

    protected function getMigrator(): Migrator
    {
        $m = app('migrator');
        $m->setConnection(config('database.default'));

        return $m;
    }

    protected function getDataMigrator(): Migrator
    {
        $m = app('migrator.data');
        $m->setConnection(config('database.default'));

        return $m;
    }

    /**
     * Find all of the possible paths that migrations exist.
     * Include looking in all of the modules database/migrations directories
     */
    public function getMigrationPaths(string $dir = 'migrations'): array
    {
        $paths = [
            'core' => App::databasePath().'/'.$dir,
        ];

        // Pre-install, the `addons` table doesn't exist yet, so the enabled
        // registry can't be queried. But create_addons_table (the core
        // migration that creates it) scans disk and inserts every module it
        // finds as enabled — so the set of addons that *will* be enabled is
        // exactly what's on disk right now. Scan disk directly rather than
        // waiting for a table that a single migration pass will create.
        $hasAddonsTable = Schema::hasTable('addons');
        $enabled = $hasAddonsTable
            ? $this->addonRegistry->enabled()->keyBy(fn (Addon $addon): string => $addon->getName())
            : collect();
        $modules = $hasAddonsTable
            ? $enabled->map(fn (Addon $addon): string => $addon->getPath())
            : $this->scanAddonPathsOnDisk();

        foreach ($modules as $name => $path) {
            if (!is_dir($path)) {
                if ($enabled->has($name)) {
                    $this->addonRegistry->disableMissing($enabled->get($name));
                }

                continue;
            }

            $module_path = $path.'/database/'.$dir;
            if (file_exists($module_path)) {
                $paths[$name] = $module_path;
            }
        }

        return $paths;
    }

    /**
     * Scan disk for addon directories, mirroring
     * AddonDiscoveryService::scanRoot()'s conventions: immediate child
     * directories of the addon root and modules/ that contain a manifest. Used only
     * pre-install, before the `addons` table exists to be the source of
     * truth (see getMigrationPaths()).
     *
     * Direct-child symlinks are permitted (the bundled links, or a dev
     * checkout linked in by hand); anything that isn't a direct child of the
     * directory it was found in, or has no manifest, is skipped.
     *
     * @return array<string, string> addon directory basename => absolute path
     */
    private function scanAddonPathsOnDisk(): array
    {
        $parser = app(ManifestParser::class);

        /** @var array<string, array{name: string, path: string}> $chosen */
        $chosen = [];

        foreach (AddonRoots::scanned() as $base => $skip) {
            $realBase = is_dir($base) ? realpath($base) : false;

            if ($realBase === false) {
                continue;
            }

            $this->scanAddonDirectory($parser, $base, $realBase, $skip, $chosen);
        }

        $paths = [];

        foreach ($chosen as $entry) {
            $paths[$entry['name']] = $entry['path'];
        }

        return $paths;
    }

    /**
     * Add the addons directly under $base to $chosen, passing over the entry
     * names in $skip.
     *
     * @param list<string>                                     $skip
     * @param array<string, array{name: string, path: string}> $chosen
     */
    private function scanAddonDirectory(ManifestParser $parser, string $base, string $realBase, array $skip, array &$chosen): void
    {
        foreach (File::directories($base) as $subDir) {
            if (in_array(basename((string) $subDir), $skip, true)) {
                continue;
            }

            $resolved = realpath($subDir);

            if ($resolved === false) {
                continue;
            }

            if (realpath(dirname((string) $subDir)) !== $realBase) {
                continue;
            }

            if (ManifestParser::manifestPath($resolved) === null) {
                continue;
            }

            $manifest = $parser->parse($resolved);

            // `name` is the basename as it appears in the root, NOT of the
            // resolved path: for a linked addon the caller keys migration paths
            // by the name in the root, while the path itself has to be the
            // resolved one.
            $name = basename((string) $subDir);

            // Keyed on registry_id when there is one, so two directories of one
            // addon run one set of migrations, as discovery loads one. A
            // manifest with no registry_id falls back to its basename; it is
            // still included, since this scan runs pre-install, where a
            // manifest predating the registry_id requirement is exactly what
            // turns up.
            $key = $manifest instanceof AddonManifest ? 'id:'.$manifest->registryId : 'dir:'.$name;

            $chosen[$key] ??= ['name' => $name, 'path' => $resolved];
        }
    }

    /**
     * Return what migrations are available
     */
    public function migrationsAvailable(): array
    {
        $migrator = $this->getMigrator();
        $migration_dirs = $this->getMigrationPaths('migrations');

        $availMigrations = [];
        $runFiles = [];

        try {
            $runFiles = $migrator->getRepository()->getRan();
        } catch (Exception) {
        } // Skip database run initialized

        $files = $migrator->getMigrationFiles(array_values($migration_dirs));

        foreach ($files as $filename => $filepath) {
            if (in_array($filename, $runFiles, true)) {
                continue;
            }

            $availMigrations[] = $filepath;
        }

        // Log::info('Migrations available: '.count($availMigrations));

        return $availMigrations;
    }

    /**
     * Run all of the migrations that are available. Just call artisan since
     * it looks into all of the module directories, etc
     */
    public function runAllMigrations(): string
    {
        Artisan::call('migrate', ['--force' => true, '--realpath' => true, '--path' => $this->getMigrationPaths()]);

        return trim(Artisan::output());
    }

    public function runAllMigrationsWithStreaming(Closure $streamCallback): int
    {
        $command = ['migrate', '--force', '--realpath'];

        foreach ($this->getMigrationPaths() as $path) {
            $command[] = '--path='.$path;
        }

        return app(StreamedCommandsService::class)->streamArtisanCommand($command, $streamCallback);
    }

    /**
     * Roll back (drop) all of an addon's schema migrations.
     *
     * Runs each migration's down() in reverse and removes its record from the
     * migrations table, so the addon's tables are dropped. Used when
     * uninstalling an addon with table removal requested. No-op when the addon
     * ships no migrations directory.
     *
     * @param string|null $addonPath Directory to read the migrations from, when the
     *                               addon's files have been moved from `$addon->getPath()`.
     */
    public function rollbackAddonMigrations(Addon $addon, ?string $addonPath = null): void
    {
        $path = ($addonPath ?? $addon->getPath()).'/database/migrations';

        if (!is_dir($path)) {
            return;
        }

        Artisan::call('migrate:reset', [
            '--force'    => true,
            '--realpath' => true,
            '--path'     => [$path],
        ]);
    }

    /**
     * Drop the given tables, ignoring any that don't exist.
     *
     * Tables are dropped in reverse declared order with foreign-key constraints
     * disabled, so intra-addon references don't block removal. This is the
     * uninstall path driven by an addon's declared `database.tables` contract —
     * it does not rely on the migrations' down() methods.
     *
     * @param list<string> $tables
     */
    public function dropAddonTables(array $tables): void
    {
        if ($tables === []) {
            return;
        }

        $schema = Schema::connection(config('database.default'));

        $schema->disableForeignKeyConstraints();

        try {
            foreach (array_reverse($tables) as $table) {
                $schema->dropIfExists($table);
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }
    }

    /**
     * Remove an addon's migration records from the migrations table without
     * running their down() methods.
     *
     * Matches records by the migration filenames present in the addon's
     * `database/migrations` directory, so a later reinstall re-runs them against
     * the freshly dropped tables. No-op when the addon ships no migrations.
     *
     * @param string|null $addonPath Directory to read the migrations from, when the
     *                               addon's files have been moved from `$addon->getPath()`.
     */
    public function purgeAddonMigrationRecords(Addon $addon, ?string $addonPath = null): void
    {
        $path = ($addonPath ?? $addon->getPath()).'/database/migrations';

        if (!is_dir($path)) {
            return;
        }

        $migrator = $this->getMigrator();
        $names = array_keys($migrator->getMigrationFiles([$path]));

        if ($names === []) {
            return;
        }

        $repository = $migrator->getRepository();

        foreach ($names as $name) {
            $repository->delete((object) ['migration' => $name]);
        }
    }

    /**
     * Return what migrations are available
     */
    public function dataMigrationsAvailable(): array
    {
        $migrator = $this->getDataMigrator();
        $migration_dirs = $this->getMigrationPaths('migrations_data');

        $availMigrations = [];
        $runFiles = [];

        try {
            $runFiles = $migrator->getRepository()->getRan();
        } catch (Exception) {
        } // Skip database run initialized

        $files = $migrator->getMigrationFiles(array_values($migration_dirs));

        foreach ($files as $filename => $filepath) {
            if (in_array($filename, $runFiles, true)) {
                continue;
            }

            $availMigrations[] = $filepath;
        }

        // Log::info('Migrations available: '.count($availMigrations));

        // dd($availMigrations);
        return $availMigrations;
    }

    /**
     * Run all of the migrations that are available. Just call artisan since
     * it looks into all of the module directories, etc
     */
    public function runAllDataMigrations(): string
    {
        Artisan::call('migrate-data', ['--force' => true, '--realpath' => true, '--path' => $this->getMigrationPaths('migrations_data')]);

        return trim(Artisan::output());
    }

    public function runAllDataMigrationsWithStreaming(Closure $streamCallback): int
    {
        $command = ['migrate-data', '--force', '--realpath'];

        foreach ($this->getMigrationPaths('migrations_data') as $path) {
            $command[] = '--path='.$path;
        }

        return app(StreamedCommandsService::class)->streamArtisanCommand($command, $streamCallback);
    }
}
