<?php

/**
 * Handle the authentication for the API layer
 */

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Filament\System\Installer;
use App\Models\User;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check the app.key to see whether we're installed or not
 *
 * If the default key is set and we're not in any of the installer routes
 * show the message that we need to be installed
 */
class InstalledCheck implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // If we're in the installer, skip the installed check.
        // But if the DB isn't set up yet, invalidate any stale auth session so that
        // AuthenticateSession (which runs after this middleware) doesn't query
        // non-existent tables.
        if ($request->is('system*') || request()->is('livewire-*/update')) {
            try {
                if (!Schema::hasTable('users')) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            } catch (Exception) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $next($request);
        }

        try {
            DB::connection()->getPdo();
            if (!Schema::hasTable('users') || User::count() === 0) {
                return redirect('/system/install');
            }
        } catch (Exception) {
            return redirect('/system/install');
        }

        return $next($request);
    }
}
