<?php

declare(strict_types=1);

namespace App\Features\Tiles;

use Illuminate\Support\ServiceProvider;

/**
 * Binds the tile registry.
 */
class TilesServiceProvider extends ServiceProvider
{
    /**
     * Singleton is safe under Octane: registrations happen at boot, and the
     * built tiles are immutable.
     */
    public function register(): void
    {
        $this->app->singleton(Tiles::class);
    }
}
