<?php

declare(strict_types=1);

namespace App\Features\Themes;

use App\Features\Themes\Listeners\BumpThemeOutput;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the {@see ThemeOptions} registry once and {@see ThemeFields} once per request.
 */
class ThemeFieldsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A singleton of closures only; it holds no request state.
        $this->app->singleton(ThemeOptions::class);

        // Scoped, not a singleton: it memoises per request, and a long-running
        // Octane worker must build a fresh one each time.
        $this->app->scoped(ThemeFields::class);
    }

    public function boot(): void
    {
        Event::listen('eloquent.saved: '.ThemeSetting::class, BumpThemeOutput::class);
        Event::listen('eloquent.deleted: '.ThemeSetting::class, BumpThemeOutput::class);
    }
}
