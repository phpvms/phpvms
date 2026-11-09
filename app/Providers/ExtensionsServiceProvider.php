<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Extensions\Extensions;
use App\Support\Extensions\SlotRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the extension hub into the container. Themes (SPA and Blade) and addons
 * share it.
 *
 * The hub is a singleton; the slot list it exposes is `scoped`, so it is empty
 * at the start of every request.
 *
 * Registered ahead of the addon engine in bootstrap/providers.php so the
 * `extensions` binding exists before any addon ServiceProvider::boot() calls the
 * Extensions facade.
 */
final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('extensions', static fn (): Extensions => new Extensions());
        $this->app->scoped(SlotRegistry::class);
    }
}
