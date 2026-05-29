<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Installer\MigrationService;
use Exception;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
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
        $seeders = [
            ShieldSeeder::class,
            YamlSeeder::class,
        ];

        // Always insert the samples in the demo environment
        if (app()->environment('demo')) {
            $seeders[] = SampleDataSeeder::class;
        }

        $this->call($seeders);

        if ($this->migrationSvc->dataMigrationsAvailable()) {
            $this->migrationSvc->runAllDataMigrations();
        }
    }
}
