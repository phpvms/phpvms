<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Addons\AddonRegistry;
use App\Addons\Models\AddonManifest;
use App\Addons\Support\AddonRoots;
use App\Addons\Support\AddonVersions;
use App\Addons\Support\ManifestParser;
use App\Contracts\Service;
use App\Models\Addon;
use Closure;
use Exception;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
        $modules = Schema::hasTable('addons')
            ? $this->addonRegistry->enabled()->mapWithKeys(fn ($module): array => [$module->getName() => $module->getPath()])
            : $this->scanAddonPathsOnDisk();

        foreach ($modules as $name => $path) {
            if (!is_dir($path)) {
                Log::warning(sprintf(
                    'Addon "%s" is enabled but its path does not exist on disk: %s',
                    $name,
                    $path,
                ));

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
     * AddonDiscoveryService::scanLocation()'s conventions: immediate child
     * directories of the addon base that contain a module.json. Used only
     * pre-install, before the `addons` table exists to be the source of
     * truth (see getMigrationPaths()).
     *
     * Direct-child symlinks are permitted (e.g. modules/phpvms-acars
     * symlinked to an external checkout); anything that isn't a direct
     * child of the base, or has no module.json, is skipped.
     *
     * Scans both addon roots and picks the same copy discovery would load, so
     * the migrations that run belong to the code that will be running.
     *
     * @return array<string, string> addon directory basename => absolute path
     */
    private function scanAddonPathsOnDisk(): array
    {
        $parser = app(ManifestParser::class);
        $image = AddonRoots::image();

        /** @var array<string, array{name: string, path: string, version: ?string, fromImage: bool}> $chosen */
        $chosen = [];

        foreach (AddonRoots::all() as $base) {
            if (!is_dir($base)) {
                continue;
            }

            $realBase = realpath($base);

            if ($realBase === false) {
                continue;
            }

            $fromImage = $base === $image;

            foreach (File::directories($base) as $subDir) {
                $resolved = realpath($subDir);

                if ($resolved === false) {
                    continue;
                }

                if (realpath(dirname((string) $subDir)) !== $realBase) {
                    continue;
                }

                if (!file_exists($resolved.'/module.json')) {
                    continue;
                }

                $manifest = $parser->parse($resolved);

                // `name` is the basename as it appears in the root, NOT of the
                // resolved path: for a symlinked module (modules/Linked -> an
                // external checkout) the caller keys migration paths by the name
                // in the root, while the path itself has to be the resolved one.
                $incoming = [
                    'name'      => basename((string) $subDir),
                    'path'      => $resolved,
                    'version'   => $manifest?->version,
                    'fromImage' => $fromImage,
                ];

                // Keyed on registry_id when there is one, so the same addon in
                // both roots resolves exactly as
                // AddonDiscoveryService::scanAllRoots() resolves it: higher
                // version wins, ties to the image. Keying on directory basename
                // instead would run the wrong copy's migrations, since the roots
                // can name one addon differently (modules/Twin vs
                // storage/addons/acme-twin) — that runs both — and where the
                // names do match it would run the image copy's even though
                // discovery loads the newer installed one.
                //
                // A manifest with no registry_id has no identity to compare, so
                // it falls back to its basename and the first root wins. Such a
                // directory is still included: this scan runs pre-install, where
                // a manifest predating the registry_id requirement is exactly
                // what turns up, and dropping it would silently skip its
                // migrations.
                $key = $manifest instanceof AddonManifest
                    ? 'id:'.$manifest->registryId
                    : 'dir:'.$incoming['name'];

                $held = $chosen[$key] ?? null;

                if ($held === null) {
                    $chosen[$key] = $incoming;

                    continue;
                }

                [$imageCopy, $installedCopy] = $held['fromImage'] ? [$held, $incoming] : [$incoming, $held];

                $chosen[$key] = AddonVersions::installedSupersedesImage(
                    $installedCopy['version'],
                    $imageCopy['version'],
                ) ? $installedCopy : $imageCopy;
            }
        }

        $paths = [];

        foreach ($chosen as $entry) {
            $paths[$entry['name']] = $entry['path'];
        }

        return $paths;
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
     */
    public function rollbackAddonMigrations(Addon $addon): void
    {
        $path = $addon->getPath().'/database/migrations';

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
     */
    public function purgeAddonMigrationRecords(Addon $addon): void
    {
        $path = $addon->getPath().'/database/migrations';

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
