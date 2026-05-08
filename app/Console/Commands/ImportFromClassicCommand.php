<?php

namespace App\Console\Commands;

use App\Services\LegacyImporterService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'phpvms:importer', description: 'Import data from an older version of phpVMS')]
class ImportFromClassicCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'phpvms:importer 
                            {db_host : The legacy database host} 
                            {db_name : The legacy database name} 
                            {db_user : The legacy database user} 
                            {db_pass? : The legacy database password} 
                            {table_prefix=phpvms_ : The legacy database table prefix}';

    /**
     * Execute the console command.
     */
    public function handle(LegacyImporterService $importerSvc): int
    {
        $this->components->info('Starting legacy phpVMS import...');

        $creds = [
            'host'         => $this->argument('db_host'),
            'name'         => $this->argument('db_name'),
            'user'         => $this->argument('db_user'),
            'pass'         => $this->argument('db_pass'),
            'table_prefix' => $this->argument('table_prefix'),
        ];

        // 1. Setup Credentials
        $this->components->task('Configuring legacy database connection', function () use ($importerSvc, $creds): void {
            $importerSvc->saveCredentials($creds);
        });

        // 2. Generate Manifest
        $manifest = [];
        $this->components->task('Generating import manifest', function () use ($importerSvc, &$manifest): void {
            $manifest = $importerSvc->generateImportManifest();
        });

        if ($manifest === []) {
            $this->components->warn('No items found in the import manifest.');

            return self::SUCCESS;
        }

        // 3. Process Importers
        $this->components->info('Running individual legacy importers...');

        $hasErrors = false;

        foreach ($manifest as $record) {
            // Try to extract a clean name for the UI (e.g., "App\Importers\FlightImporter" -> "FlightImporter")
            $importerName = is_string($record['importer'])
                ? class_basename($record['importer'])
                : 'Legacy Record';

            // We no longer assign the result of ->task(). Instead, we pass &$hasErrors
            // by reference so the closure can update it if an exception occurs.
            $this->components->task('Importing '.$importerName, function () use ($importerSvc, $record, $importerName, &$hasErrors): bool {
                try {
                    $importerSvc->run($record['importer'], $record['start']);

                    return true;
                } catch (Exception $exception) {
                    Log::error(sprintf('Legacy Import Error [%s]: ', $importerName).$exception->getMessage());
                    $hasErrors = true;

                    // Returning false tells the Laravel task component to display a "FAIL" status
                    return false;
                }
            });
        }

        // 4. Final Summary
        if ($hasErrors) {
            $this->components->warn('Import completed with some errors. Please check your laravel.log for details.');

            return self::FAILURE;
        }

        $this->components->info('Legacy import completed successfully!');

        return self::SUCCESS;
    }
}
