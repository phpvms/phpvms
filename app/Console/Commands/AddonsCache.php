<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Addons\Services\AddonDiscoveryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Write the addon boot cache (bootstrap/cache/addons.php) ahead of the first
 * request.
 *
 * Web boots build it on demand, but only in boot(), after register() has
 * already read it. A fresh container has none (bootstrap/cache is not in the
 * image), so every Octane worker that starts at the same time would boot
 * without any addon, theme addons included, until it is recycled. The
 * container entrypoint runs this before the server starts.
 */
#[Signature('addons:cache')]
#[Description('Rebuild the addon boot cache (bootstrap/cache/addons.php)')]
class AddonsCache extends Command
{
    public function handle(AddonDiscoveryService $discovery): int
    {
        // Without a reachable, installed database, run() bootstraps the cache
        // from disk with every addon enabled, disabled ones included. Leave
        // that to the first web boot rather than risk it on a database that is
        // only briefly unreachable while the container starts.
        if (!installed()) {
            $this->warn('Database not reachable or phpvms not installed; boot cache left to the first request.');

            return self::FAILURE;
        }

        $discovery->run();

        $this->info('Addon boot cache rebuilt.');

        return self::SUCCESS;
    }
}
