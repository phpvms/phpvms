<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Addons\AddonRegistry;
use App\Contracts\Service;
use App\Models\Addon;
use Closure;
use Exception;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
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
     * Include looking in all of the modules Database/migrations directories
     */
    public function getMigrationPaths(string $dir = 'migrations'): array
    {
        $paths = [
            'core' => App::databasePath().'/'.$dir,
        ];

        $modules = $this->addonRegistry->enabled();
        foreach ($modules as $module) {
            if (!is_dir($module->getPath())) {
                Log::warning(sprintf(
                    'Addon "%s" is enabled but its path does not exist on disk: %s',
                    $module->getName(),
                    $module->getPath(),
                ));

                continue;
            }

            $module_path = $module->getPath().'/Database/'.$dir;
            if (file_exists($module_path)) {
                $paths[$module->getName()] = $module_path;
            }
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

    public function runAllMigrationsWithStreaming(Closure $streamCallback): void
    {
        $command = ['migrate', '--force', '--realpath'];

        foreach ($this->getMigrationPaths() as $path) {
            $command[] = '--path='.$path;
        }

        app(StreamedCommandsService::class)->streamArtisanCommand($command, $streamCallback);
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
        $path = $addon->getPath().'/Database/migrations';

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
     * `Database/migrations` directory, so a later reinstall re-runs them against
     * the freshly dropped tables. No-op when the addon ships no migrations.
     */
    public function purgeAddonMigrationRecords(Addon $addon): void
    {
        $path = $addon->getPath().'/Database/migrations';

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

    public function runAllDataMigrationsWithStreaming(Closure $streamCallback): void
    {
        $command = ['migrate-data', '--force', '--realpath'];

        foreach ($this->getMigrationPaths('migrations_data') as $path) {
            $command[] = '--path='.$path;
        }

        app(StreamedCommandsService::class)->streamArtisanCommand($command, $streamCallback);
    }
}
