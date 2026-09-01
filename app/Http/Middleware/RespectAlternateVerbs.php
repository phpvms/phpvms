<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Services\PageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Restores 405 Method Not Allowed, which the page fallback route would
 * otherwise hide.
 *
 * `RouteCollection::match()` consults fallback routes *before* it calls
 * `checkForAlternateVerbs()`. Registering a catch-all therefore means a GET
 * against a POST-only route matches the fallback and never reaches the method
 * check -- turning every method mismatch in the application into a 404.
 *
 * This has to run before anything else in the group. 405 is decided during
 * routing, so a check inside the fallback controller is already too late: by
 * then `InstalledCheck` has had its say and a bare install answers 302.
 *
 * Only requests that actually landed on a fallback pay for the route scan.
 */
class RespectAlternateVerbs implements Middleware
{
    public function __construct(private readonly PageService $pages) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->isFallback !== true) {
            return $next($request);
        }

        $alternates = $this->pages->alternateMethodsFor($request);

        if ($alternates !== []) {
            throw new MethodNotAllowedHttpException($alternates);
        }

        return $next($request);
    }
}
