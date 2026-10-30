<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds the addon boot cache, then restarts Octane's workers when this runs
 * inside one.
 *
 * Theme addons register from the boot cache inside `register()`, and a web
 * boot only writes that cache after registration. On a fresh install, or an
 * update that ships a new bundled addon (airside), every worker already
 * running booted without it, so the pilot pages would fail until each worker
 * was recycled. The installer and the updater call this when they finish.
 *
 * Never throws: the install or update has already succeeded, and the cache
 * rebuilds itself on the next web boot.
 */
class AddonCacheRefresher
{
    public function __construct(private readonly OctaneReloader $octane) {}

    public function refresh(): void
    {
        try {
            Artisan::call('addons:cache');
            $this->octane->reload();
        } catch (Throwable $throwable) {
            Log::warning('Could not rebuild the addon boot cache', ['exception' => $throwable]);
        }
    }
}
