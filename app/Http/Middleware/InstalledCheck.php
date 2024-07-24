<?php
/**
 * Handle the authentication for the API layer
 */

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Filament\System\Installer;
use App\Models\User;
use App\Providers\Filament\SystemPanelProvider;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        if ((empty($key) || $key === 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=') && !$request->is('system*')) {
            return redirect('/system/install');
        }

        return $next($request);
    }
}
