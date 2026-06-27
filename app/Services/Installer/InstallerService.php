<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InstallerService extends Service
{
    public function __construct(
        private readonly MigrationService $migrationSvc,
        private readonly SeederService $seederSvc
    ) {}

    /**
     * Invalidate the per-request upgrade-pending cache. Call after running
     * migrations or seeds so the next check reflects the updated state.
     */
    public function invalidateUpgradeCache(): void
    {
        Cache::store('array')->forget('upgrade_pending');
    }

    /**
     * Check to see if there is an upgrade pending by checking the migrations or seeds.
     * Result is cached per-request (array store) to avoid repeated DB queries on
     * pages that call this multiple times (e.g. middleware + page mount).
     */
    public function isUpgradePending(): bool
    {
        return Cache::store('array')->rememberForever('upgrade_pending', fn (): bool => $this->checkUpgradePending());
    }

    private function checkUpgradePending(): bool
    {
        $pendingMigrations = count($this->migrationSvc->migrationsAvailable());
        if ($pendingMigrations > 0) {
            Log::info('Found '.$pendingMigrations.' pending migrations, update available');

            return true;
        }

        $pendingDataMigrations = count($this->migrationSvc->dataMigrationsAvailable());
        if ($pendingDataMigrations > 0) {
            Log::info('Found '.$pendingDataMigrations.' pending data migrations, update available');

            return true;
        }

        if ($this->seederSvc->seedsPending()) {
            Log::info('Found seeds pending, update available');

            return true;
        }

        return false;
    }

    /**
     * Clear whatever caches we can by calling Artisan
     */
    public function clearCaches(): void
    {
        Artisan::call('optimize:clear');
    }
}
