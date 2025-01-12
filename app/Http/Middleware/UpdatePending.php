<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Services\Installer\InstallerService;
use Closure;
use Illuminate\Http\Request;

/**
 * Determine if an update is pending by checking in with the Installer service.
 */
class UpdatePending implements Middleware
{
    private $installerSvc;

    public function __construct(InstallerService $installerSvc)
    {
        $this->installerSvc = $installerSvc;
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->installerSvc->isUpgradePending()) {
            return redirect('/update');
        }

        return $next($request);
    }
}
