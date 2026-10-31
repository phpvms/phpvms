<?php

declare(strict_types=1);

namespace App\Features\Themes;

use Illuminate\Support\ServiceProvider;

/**
 * Binds {@see ThemeFields} once per request.
 */
class ThemeFieldsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not a singleton: it memoises per request, and a long-running
        // Octane worker must build a fresh one each time.
        $this->app->scoped(ThemeFields::class);
    }
}
