<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use Database\Seeders\SettingsSeeder;

class SeederService extends Service
{
    public function __construct(
        private readonly DatabaseService $databaseSvc
    ) {}

    /**
     * Synchronize all the seed files, run this after the migrations
     * and on first install.
     *
     * @throws \Exception
     */
    public function syncAllSeeds(): void
    {
        // Seed base
        $this->databaseSvc->seedFromYamlFile(database_path('seeders/base/base.yml'));
    }

    /**
     * See if there are any seeds that are out of sync
     */
    public function seedsPending(): bool
    {
        return (new SettingsSeeder())->settingsPending();
    }
}
