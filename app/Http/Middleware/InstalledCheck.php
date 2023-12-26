<?php
/**
 * Handle the authentication for the API layer
 */

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
use Illuminate\Http\Request;

/**
 * Check the app.key to see whether we're installed or not
 *
 * If the default key is set and we're not in any of the installer routes
 * show the message that we need to be installed
 */
class InstalledCheck implements Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = config('app.key');
        if ((empty($key) || $key === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=')
            && !$request->is(['install', 'install/*'])
            && !$request->is(['update', 'update/*'])
        ) {
            return response(view('system.errors.not_installed'));
        }

        if (!empty($key) && $key !== 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY='
            && $request->is(['install', 'install/*'])
        ) {
            return response(view('system.installer.errors.already-installed'));
        }

        return $next($request);
    }
}
