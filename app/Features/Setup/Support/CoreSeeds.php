<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final readonly class CoreSeeds
{
    /**
     * Never seeded: the settings table does not exist yet.
     */
    public function pending(): bool
    {
        return !Schema::hasTable('settings');
    }

    /**
     * Upgrades only sync permissions; base data (ranks, fares, ratings) is seeded on fresh installs.
     *
     * @param ?Closure(string): void $log
     */
    public function sync(?Closure $log = null): void
    {
        if (Artisan::call('permission:sync') !== 0) {
            throw new RuntimeException('permission:sync failed: '.trim(Artisan::output()));
        }

        if ($log instanceof Closure) {
            $log('Synced permissions');
        }
    }
}
