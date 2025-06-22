<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
use Illuminate\Http\Request;

/**
 * Determine if an update is pending by checking in with the Installer service
 */
class UpdatePending implements Middleware
{
    public function __construct(private readonly \App\Services\Installer\InstallerService $installerSvc) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->installerSvc->isUpgradePending()) {
            return redirect('/system/update');
        }

        return $next($request);
    }
}
