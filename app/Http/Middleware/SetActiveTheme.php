<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
use Exception;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Read the current theme from the settings (set in admin), and set it.
 *
 * SPA themes (theme.json: kind = "spa") are handled transparently: after
 * Theme::set() the active theme's kind is readable via theme_kind(), which
 * the Response::themed() macro uses to branch between Inertia and Blade.
 * No per-request state is written here — reading theme_kind() anywhere in
 * the same request is Octane-safe because this middleware always resets the
 * Theme singleton before the controller runs.
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
     *
     * When the resolved theme declares kind = "spa" in its theme.json, the
     * Theme singleton after this method returns carries that metadata in
     * $theme->settings. Callers use theme_kind() / theme_setting() to read
     * it; the SPA render path is engaged by Response::themed() (not here).
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

        if ($request->is(self::$skip)) {
            // Skipped paths don't pick a per-request theme but still need a
            // deterministic baseline under Octane (see method PHPDoc).
            // SPA theming is never applied to admin/api/importer/install/update.
            Theme::set(config('themes.default'));

            return;
        }

        Theme::set($theme);
    }
}
