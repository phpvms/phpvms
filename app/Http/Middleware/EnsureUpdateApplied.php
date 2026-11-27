<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\RequestSiteState;
use App\Filament\Pages\SystemUpdates;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Sends a logged-in admin to the update page while core setup work is pending.
 * Guests still get the login page, so nothing is queued for them.
 */
class EnsureUpdateApplied implements Middleware
{
    public function __construct(private readonly RequestSiteState $state) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->state->of($request) !== SiteState::UpdatePending) {
            return $next($request);
        }

        $user = Filament::auth()->user();

        if (!$user instanceof User || !$user->hasAdminAccess()) {
            return $next($request);
        }

        $panel = Filament::getPanel('admin');

        if ($request->routeIs(SystemUpdates::getRouteName($panel), $panel->generateRouteName('auth.logout'))) {
            return $next($request);
        }

        return redirect(SystemUpdates::getUrl(panel: 'admin'));
    }
}
