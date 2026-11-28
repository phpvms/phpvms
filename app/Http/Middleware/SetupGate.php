<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Exceptions\SiteUnavailable;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\LivewireUpdate;
use App\Features\Setup\Support\RequestSiteState;
use App\Support\InstallMarker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global, ahead of every StartSession, and Livewire persistent middleware so a replayed admin snapshot is gated too.
 * A fresh database has no sessions table, so the installer runs on file sessions; and admin pages route the
 * states that need neither session nor auth (a 7.x database lacks the roles tables panel auth reads).
 */
class SetupGate implements Middleware
{
    public function __construct(
        private readonly RequestSiteState $state,
        private readonly InstallMarker $marker,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $panel = $this->isPanelRequest($request);

        // A trusted marker proves the site is installed; no state lookup is needed off the panel.
        if (!$panel && $this->marker->isTrusted()) {
            return $next($request);
        }

        $state = $this->state->of($request);

        if (!$this->marker->isTrusted() && in_array($state, [SiteState::Install, SiteState::FirstUser], true)) {
            config(['session.driver' => 'file']);
        }

        return ($panel ? $this->answer($request) : null) ?? $next($request);
    }

    /**
     * The response for a state the panel does not serve, or null when the request may go on.
     */
    public function answer(Request $request): ?Response
    {
        return match ($this->state->of($request)) {
            SiteState::Unavailable                       => LivewireUpdate::active() ? abort(503) : SiteUnavailable::response(),
            SiteState::Install                           => $request->is('admin/install') ? null : redirect('/admin/install'),
            SiteState::FirstUser                         => $request->is('admin/first-user') ? null : redirect('/admin/first-user'),
            SiteState::V7Upgrade                         => redirect('/system/upgrade'),
            SiteState::UpdatePending, SiteState::Current => null,
        };
    }

    // The Livewire route is not an admin path; its replayed snapshots reach this as the page they came from.
    private function isPanelRequest(Request $request): bool
    {
        return $request->is('admin', 'admin/*') && !$request->is(...PreventRequestsDuringSetup::NOT_PANEL_ROUTES);
    }
}
