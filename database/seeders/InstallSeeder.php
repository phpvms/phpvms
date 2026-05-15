<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Installer\MigrationService;
use Exception;
use Illuminate\Database\Seeder;

class InstallSeeder extends Seeder
{
    public function __construct(
        private readonly MigrationService $migrationSvc
    ) {}

    /**
     * Run the database seeds.
     *
     * @throws Exception
     */
    public function run(): void
    {
        $this->call([
            ShieldSeeder::class,
            YamlSeeder::class,
        ]);

        // Make sure any data migrations that need to be run are run/cleared out
        if ($this->migrationSvc->dataMigrationsAvailable()) {
            $this->migrationSvc->runAllDataMigrations();
        }
    }
}
