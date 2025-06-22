<?php

use App\Database\seeds\ShieldSeeder;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** @var MigrationService */
    private $migrationSvc;

    /** @var SeederService */
    private $seederSvc;

    public function __construct()
    {
        $this->migrationSvc = app(MigrationService::class);
        $this->seederSvc = app(SeederService::class);
    }

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

        // Then sync all of the seeds
        $this->seederSvc->syncAllSeeds();

        $this->call([
            ShieldSeeder::class,
        ]);
    }
}
