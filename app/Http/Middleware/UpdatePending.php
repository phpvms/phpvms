<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Services\Installer\InstallerService;
use Closure;
use Illuminate\Http\Request;

/**
 * Determine if an update is pending by checking in with the Installer service
 */
class UpdatePending implements Middleware
{
    public function __construct(private readonly InstallerService $installerSvc) {}

    public function handle(Request $request, Closure $next)
    {
        // Gate on *core* pending only (migrations/data-migrations/core seeds).
        // Addon seeds are intentionally excluded: a broken addon seeder that can
        // never complete must not trap every panel request in a redirect loop.
        if ($this->installerSvc->isCoreUpgradePending()) {
            return redirect('/system/update');
        }

        return $next($request);
    }
}
