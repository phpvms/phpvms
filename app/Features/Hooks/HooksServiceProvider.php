<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use Illuminate\Support\ServiceProvider;

/**
 * Binds the hook dispatcher. `<x-render-hook>` is App\View\Components\RenderHook,
 * found by Blade's class-component discovery like the other core components.
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
