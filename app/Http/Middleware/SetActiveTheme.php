<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
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
     * Set the theme for the current middleware
     */
    public function setTheme(Request $request): void
    {
        if ($request->is(self::$skip)) {
            return;
        }

        try {
            $theme = setting('general.theme', 'seven');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $theme = 'seven';
        }

        if (!empty($theme)) {
            Theme::set($theme);
        }
    }
}
