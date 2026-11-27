<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Features\Setup\Support\LivewireUpdate;
use Closure;
use Illuminate\Http\Request;

/**
 * Answers public pages with a "site is being set up" page until the setup
 * wizard is done, so the airline-less first admin never reaches a theme view.
 * Livewire updates are gated by this class being persistent middleware.
 */
class EnsureSiteSetUp implements Middleware
{
    public function handle(Request $request, Closure $next)
    {
        if (setting('general.setup_completed', true)) {
            return $next($request);
        }

        // Panel pages have their own gates; the Livewire route is gated through its snapshot's page.
        // Plain web routes under admin/ (route-forge API, page preview) are not panel pages.
        $name = $request->route()?->getName();

        if ((is_string($name) && str_starts_with($name, 'filament.')) || $request->is('livewire-*/update') || EnsureSetupComplete::onlyExemptComponents()) {
            return $next($request);
        }

        if (LivewireUpdate::active()) {
            abort(503);
        }

        return response()->view('system.errors.database_error', [
            'title'   => __('installer.setup_wizard.being_set_up'),
            'message' => __('installer.setup_wizard.being_set_up_text'),
        ], 503);
    }
}
