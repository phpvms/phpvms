<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
use Exception;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Read the current theme from the settings (set in admin), and set it
 */
class SetActiveTheme implements Middleware
{
    private static array $skip = [
        'admin',
        'admin/*',
        'api',
        'api/*',
        'importer',
        'importer/*',
        'install',
        'install/*',
        'update',
        'update/*',
    ];

    /**
     * Handle the request
     *
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $this->setTheme($request);

        return $next($request);
    }

    /**
     * Set the theme for the current middleware.
     *
     * Octane keeps the application booted across requests, so the underlying
     * igaster/laravel-theme singleton retains whatever theme the previous
     * request set. Skipped paths (admin/api/install) must therefore reset
     * to the configured default explicitly — early-returning would let a
     * prior frontend request's theme leak into the next admin/api response.
     */
    public function setTheme(Request $request): void
    {
        try {
            $theme = setting('general.theme', 'seven');
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            $theme = 'seven';
        }

        if (empty($theme)) {
            $theme = config('themes.default');
        }

        /*if ($request->is(self::$skip)) {
            // Skipped paths don't pick a per-request theme but still need a
            // deterministic baseline under Octane (see method PHPDoc).
            Theme::set($theme);

            return;
        }*/

        Theme::set($theme);
    }
}
