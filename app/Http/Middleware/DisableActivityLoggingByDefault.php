<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resets activity logging to "disabled" at the start of every request.
 *
 * Octane keeps the framework booted across requests, so a boot-time
 * activity()->disableLogging() runs only once per worker. Routes / panels
 * that flip logging on (EnableActivityLogging middleware, Filament's
 * admin panel bootUsing) would then leave logging on for every subsequent
 * request the worker handles. This middleware re-applies the default at
 * the start of each request so the EnableActivityLogging middleware and
 * Filament's per-panel toggle remain the explicit opt-ins.
 *
 * @phpstan-type Handler Closure(Request): Response
 */
class DisableActivityLoggingByDefault
{
    /**
     * @param Handler $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        activity()->disableLogging();

        return $next($request);
    }
}
