<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Exceptions\SiteUnavailable;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\LivewireUpdate;
use App\Features\Setup\Support\RequestSiteState;
use Closure;
use Illuminate\Http\Request;

/**
 * First in the panel, before the session and auth: routes the states that need neither.
 * A 7.x database lacks the roles tables panel auth reads, and a fresh one has no sessions table.
 */
class EnsureDatabaseCurrent implements Middleware
{
    public function __construct(private readonly RequestSiteState $state) {}

    public function handle(Request $request, Closure $next)
    {
        return match ($this->state->of($request)) {
            SiteState::Unavailable                       => LivewireUpdate::active() ? abort(503) : SiteUnavailable::response(),
            SiteState::Install                           => $request->is('admin/install') ? $next($request) : redirect('/admin/install'),
            SiteState::FirstUser                         => $request->is('admin/first-user') ? $next($request) : redirect('/admin/first-user'),
            SiteState::V7Upgrade                         => redirect('/system/upgrade'),
            SiteState::UpdatePending, SiteState::Current => $next($request),
        };
    }
}
