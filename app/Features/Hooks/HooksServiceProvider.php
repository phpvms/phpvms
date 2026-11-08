<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use Illuminate\Support\ServiceProvider;

/**
 * Binds the hook dispatcher.
 *
 * Registered before AddonServiceProvider in bootstrap/providers.php, so the
 * binding exists when addon providers discover their hook listeners in boot().
 */
class HooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HookDispatcher::class);
    }
}
