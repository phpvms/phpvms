<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use phpseclib3\Crypt\RSA;

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
     * Whether a *core* update is pending: schema migrations, data migrations, or
     * core seeds. Deliberately excludes addon seeds.
     *
     * This is what gates the panel via UpdatePending — an addon whose seeder can
     * never complete (missing class, bad SQL) would otherwise leave addonSeeds
     * pending forever and trap every panel request in a redirect loop to
     * /system/update. Addon seeds still run during an update; they just don't
     * lock you out of the panel.
     */
    public function isCoreUpgradePending(): bool
    {
        if ($this->migrationSvc->migrationsAvailable() !== []) {
            return true;
        }

        if ($this->migrationSvc->dataMigrationsAvailable() !== []) {
            return true;
        }

        return $this->seederSvc->coreSeedsPending();
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

        $publicKey = Passport::keyPath('oauth-public.key');
        $privateKey = Passport::keyPath('oauth-private.key');

        if (file_exists($publicKey) && file_exists($privateKey)) {
            return;
        }

        // Generate the keypair directly instead of Artisan::call('passport:keys'):
        // Passport only registers its console commands under runningInConsole(), so
        // that command does not exist inside the web-based installer request and
        // calling it throws CommandNotFoundException. This mirrors KeysCommand.
        $key = RSA::createKey(4096);

        file_put_contents($publicKey, (string) $key->getPublicKey());
        file_put_contents($privateKey, (string) $key);

        if (!windows_os()) {
            @chmod($publicKey, 0660);
            @chmod($privateKey, 0600);
        }

        Log::info('Generated Passport encryption keys');
    }
}
