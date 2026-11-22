<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Pages\SystemUpdates;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Send the panel to the updater while core setup work is pending
 */
class UpdatePending implements Middleware
{
    public function __construct(
        private readonly DetectPendingWork $detect,
        private readonly UpgradeCompleted $completed,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // Checked first so a lost marker self-heals on a current 8.x site too (see UpgradeCompleted).
        $completed = $this->completed->exists();

        // Gate on *core* pending only (migrations/data-migrations/core seeds).
        // Addon seeds are intentionally excluded: a broken addon seeder that can
        // never complete must not trap every panel request in a redirect loop.
        if (!$this->detect->handle()->blocksPanel()) {
            return $next($request);
        }

        if (!$completed) {
            return redirect('/system/upgrade');
        }

        // 8.x: admins start the run from the panel; guests still get to log in.
        if ($request->routeIs('filament.admin.auth.login', 'filament.admin.auth.logout', SystemUpdates::getRouteName())
            || Gate::denies('access_admin')) {
            return $next($request);
        }

        return redirect(SystemUpdates::getUrl(panel: 'admin'));
    }
}
