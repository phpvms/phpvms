<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Exception;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function __construct(
        private readonly MigrationService $migrationSvc,
        private readonly SeederService $seederSvc
    ) {}

    /**
     * Run the database seeds.
     *
     * @throws Exception
     */
    public function run(): void
    {
        // Make sure any migrations that need to be run are run/cleared out
        if ($this->migrationSvc->migrationsAvailable()) {
            $this->migrationSvc->runAllMigrations();
        }

        // Then sync all the seeds
        $this->seederSvc->syncAllSeeds();

        $this->call([
            ShieldSeeder::class,
        ]);
    }
}
