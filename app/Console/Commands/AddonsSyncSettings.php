<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AddonSettingSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'phpvms:addons-sync-settings', description: "Sync enabled addons' declared settings into the addon_settings table")]
class AddonsSyncSettings extends Command
{
    /**
     * Upsert every enabled addon's declared settings schema.
     *
     * Deterministic counterpart to the boot-time sync — for CI, deploys, and
     * tests, where the web-boot prime path is skipped (D-17). Idempotent:
     * preserves user-edited values, reconciles metadata.
     */
    public function handle(AddonSettingSyncService $sync): int
    {
        try {
            $this->components->task('Syncing addon settings', static function () use ($sync): void {
                $sync->sync();
            });
        } catch (Throwable $throwable) {
            $this->components->error('Addon settings sync failed: '.$throwable->getMessage());
            Log::error('Addon settings sync failed', ['exception' => $throwable]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
