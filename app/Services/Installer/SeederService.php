<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use Database\Seeders\BaseDataSeeder;
use Database\Seeders\SettingsSeeder;

class SeederService extends Service
{
    /**
     * Synchronize all the seed files, run this after the migrations
     * and on first install.
     */
    public function syncAllSeeds(): void
    {
        app(BaseDataSeeder::class)->run();
        app(SettingsSeeder::class)->run();
    }

    /**
     * See if there are any seeds that are out of sync
     */
    public function seedsPending(): bool
    {
        return (new SettingsSeeder())->settingsPending();
    }
}
