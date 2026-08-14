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
            $theme = setting('general.theme', config('themes.default'));
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            $theme = config('themes.default');
        }

        if (empty($theme)) {
            $theme = config('themes.default');
        }

        if ($request->is(self::$skip)) {
            // Skipped paths don't pick a per-request theme but still need a
            // deterministic baseline under Octane (see method PHPDoc).
            // SPA theming is never applied to admin/api/importer/install/update.
            Theme::set($this->registeredTheme(config('themes.default')));

            return;
        }

        Theme::set($this->registeredTheme($theme));
    }

    /**
     * Resolve a theme name to one that is actually registered.
     *
     * Themes::set() does not fail on an unknown name — it builds a parentless
     * Theme, so the `extends` chain silently disappears and every themed view
     * 404s at the finder with "View [...] not found". That is reachable in
     * practice because the theme cache (bootstrap/cache/themes.php) is only
     * rebuilt when the file is missing: an install that gains a theme after
     * the cache was written never sees it.
     *
     * So rebuild the cache once — that alone fixes the stale-cache case — and
     * fall back to a theme that exists rather than serving a 500.
     */
    private function registeredTheme(string $theme): string
    {
        if (Theme::exists($theme)) {
            return $theme;
        }

        Theme::rebuildCache();
        Theme::scanThemes();

        if (Theme::exists($theme)) {
            return $theme;
        }

        // all() is a list of Theme objects, not a name-keyed map.
        $fallback = Theme::all()[0]->name ?? null;

        Log::warning(sprintf(
            'Theme "%s" is not installed; falling back to "%s".',
            $theme,
            $fallback ?? 'none',
        ));

        return $fallback ?? $theme;
    }
}
