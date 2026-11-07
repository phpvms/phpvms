<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Exceptions\SiteUnavailable;
use App\Models\User;
use App\Support\InstallMarker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Answer with a 503 error page while the site cannot serve requests.
 *
 * The install marker file (see InstallMarker) is the first word: when it is
 * trusted, the request goes straight through and the database is not asked.
 * Otherwise the database decides: unreachable means the site-down page, no
 * `users` table or no users means the "not set up" page. It never redirects:
 * a database outage must not look like a wiped install, so the installer is
 * only offered as a plain link on the "not set up" page.
 */
class InstalledCheck implements Middleware
{
    public function __construct(private readonly InstallMarker $marker) {}

    public function handle(Request $request, Closure $next): Response
    {
        // If we're in the installer, skip this
        // Also skip if this is a livewire update (might be called from the system)
        if ($request->is('system*') || $request->is('livewire-*/update')) {
            return $next($request);
        }

        if ($this->marker->isTrusted()) {
            return $next($request);
        }

        try {
            DB::connection()->getPdo();
            $isSetUp = Schema::hasTable('users') && User::query()->count() > 0;
        } catch (Throwable $throwable) {
            report($throwable);

            return SiteUnavailable::response();
        }

        if (!$isSetUp) {
            return response()->view('system.errors.database_error', [
                'title'   => __('errors.not_installed.title'),
                'message' => __('errors.not_installed.message'),
                'link'    => [
                    'url'  => '/system/install',
                    'text' => __('errors.not_installed.link'),
                ],
            ], 503);
        }

        // Sites installed before the marker existed get it on their first request.
        if (!$this->marker->exists()) {
            $this->marker->write();
        }

        return $next($request);
    }
}
