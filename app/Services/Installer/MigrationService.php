<?php

namespace App\Services\Installer;

use App\Contracts\Service;
use Exception;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Process\Process;

class MigrationService extends Service
{
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
     *
     * @param mixed $dir
     */
    public function getMigrationPaths($dir = 'migrations'): array
    {
        $paths = [
            'core' => App::databasePath().'/'.$dir,
        ];

        $modules = Module::allEnabled();
        foreach ($modules as $module) {
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
        } catch (Exception $e) {
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
        // A little ugly, run the main migration first, this makes sure the migration table is there
        $output = '';

        Artisan::call('migrate', ['--force' => true]);
        $output .= trim(Artisan::output());

        // Then get any remaining migrations that are left over
        // Due to caching or whatever reason, the migrations are not all loaded when Artisan first
        // runs. This is likely a side effect of the database being used as the module activator,
        // and the list of migrations being pulled before the initial modules are populated
        $migrator = $this->getMigrator();
        $availMigrations = $this->migrationsAvailable();

        if ($availMigrations !== []) {
            Log::info('Running '.count($availMigrations).' available migrations');
            $ret = $migrator->run($availMigrations);
            Log::info('Ran '.count($ret).' migrations');

            return $output."\n".implode("\n", $ret);
        }

        return $output;
    }

    public function runAllMigrationsWithStreaming(\Closure $streamCallback): void
    {
        // Inspired by runAllMigrations() but instead we're streaming the output to the callback (and then the browser) live

        // First, run the main migration to ensure the migration table is there
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $command = [$php, $artisan, 'migrate', '--force'];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) use ($streamCallback) {
            $streamCallback($buffer);
        });

        // Then get any remaining migrations that are left over
        $migrator = $this->getMigrator();
        $availMigrations = $this->migrationsAvailable();
        $this->runMigrationsWithStreaming($migrator, $availMigrations, $streamCallback);
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
        } catch (Exception $e) {
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
        $output = '';

        // Then get any remaining migrations that are left over
        // Due to caching or whatever reason, the migrations are not all loaded when Artisan first
        // runs. This is likely a side effect of the database being used as the module activator,
        // and the list of migrations being pulled before the initial modules are populated
        $migrator = $this->getDataMigrator();
        $availMigrations = $this->dataMigrationsAvailable();

        if ($availMigrations !== []) {
            Log::info('Running '.count($availMigrations).' available migrations');
            $ret = $migrator->run($availMigrations);
            Log::info('Ran '.count($ret).' migrations');

            return $output."\n".implode("\n", $ret);
        }

        return $output;
    }

    public function runAllDataMigrationsWithStreaming(\Closure $streamCallback): void
    {
        // Inspired by runAllDataMigrations() but instead we're streaming the output to the callback (and then the browser) live

        $migrator = $this->getDataMigrator();
        $availMigrations = $this->dataMigrationsAvailable();
        $this->runMigrationsWithStreaming($migrator, $availMigrations, $streamCallback);
    }

    protected function runMigrationsWithStreaming(Migrator $migrator, array $migrations, \Closure $streamCallback): void
    {
        $totalRan = 0;

        if ($migrations !== []) {
            Log::info('Running '.count($migrations).' available migrations');
            foreach ($migrations as $migration) {
                $ret = $migrator->run([$migration]);
                $streamCallback(implode("\n", $ret));
                $totalRan += count($ret);
            }
            Log::info('Ran '.$totalRan.' migrations');
        }
    }
}
