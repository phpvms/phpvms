<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class InstallerService extends Service
{
    public function __construct(
        private readonly MigrationService $migrationSvc,
        private readonly SeederService $seederSvc
    ) {}

    /**
     * Check to see if there is an upgrade pending by checking the migrations or seeds
     */
    public function isUpgradePending(): bool
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

    /**
     * Ensure Laravel Passport has encryption keys to sign OAuth2 tokens.
     *
     * Idempotent, so it is safe to call on both install and upgrade:
     *  - if the keys are provided via env (PASSPORT_PRIVATE_KEY/PUBLIC_KEY),
     *    e.g. multi-node or Octane deployments, there is nothing to generate;
     *  - if the key files already exist on disk, we leave them untouched;
     *  - otherwise we generate them, so operators never have to run
     *    `php artisan passport:keys` by hand.
     */
    public function ensurePassportKeys(): void
    {
        if (config('passport.private_key') && config('passport.public_key')) {
            return;
        }

        if (file_exists(storage_path('oauth-private.key')) && file_exists(storage_path('oauth-public.key'))) {
            return;
        }

        Artisan::call('passport:keys', ['--force' => true]);
        Log::info('Generated Passport encryption keys');
    }
}
