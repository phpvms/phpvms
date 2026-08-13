<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Skylight\Skylight;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the skylight SPA extension hub into the container.
 *
 * The hub is a singleton so that addon providers (which boot after this one
 * registers) accumulate their widget/slot registrations onto the SAME instance
 * that HandleInertiaRequests later reads when serializing shared props.
 *
 * Registered ahead of the addon engine in bootstrap/providers.php so the
 * `skylight` binding exists before any addon ServiceProvider::boot() calls the
 * Skylight facade.
 */
final class SkylightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('skylight', static fn (): Skylight => new Skylight());
    }
}
