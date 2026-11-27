<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\RequestSiteState;
use App\Support\InstallMarker;
use Closure;
use Illuminate\Http\Request;

/**
 * Global, ahead of every StartSession: a fresh database has no sessions table yet, so the installer
 * runs on file sessions. An installed site's marker answers first, so it costs no query.
 */
class UseFileSessionWhileFresh implements Middleware
{
    public function __construct(
        private readonly InstallMarker $marker,
        private readonly RequestSiteState $state,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (!$this->marker->isTrusted() && in_array($this->state->of($request), [SiteState::Install, SiteState::FirstUser], true)) {
            config(['session.driver' => 'file']);
        }

        return $next($request);
    }
}
