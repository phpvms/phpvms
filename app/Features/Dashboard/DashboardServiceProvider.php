<?php

declare(strict_types=1);

namespace App\Features\Dashboard;

use Illuminate\Support\ServiceProvider;

/**
 * Binds the dashboard widget registry.
 */
class DashboardServiceProvider extends ServiceProvider
{
    /**
     * Singleton is safe under Octane: registrations happen at boot, and the
     * built widgets are immutable.
     */
    public function register(): void
    {
        $this->app->singleton(DashboardWidgets::class);
    }
}
