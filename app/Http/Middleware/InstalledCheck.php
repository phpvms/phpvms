<?php

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Exceptions\SiteUnavailable;
use App\Features\Setup\Support\LivewireUpdate;
use App\Models\User;
use App\Support\InstallMarker;
use Closure;
use Illuminate\Http\JsonResponse;
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
 *
 * Once installed, public pages answer a "being set up" 503 until the setup
 * wizard is done. It is also Livewire persistent middleware, so updates from
 * a public page are gated too.
 */
class InstalledCheck implements Middleware
{
    public function __construct(private readonly InstallMarker $marker) {}

    public function handle(Request $request, Closure $next): Response
    {
        // If we're in the installer, skip this
        // Also skip if this is a livewire update (might be called from the system)
        // The web cron finishes setup runs, so it must answer before the first user exists
        if ($request->is('system*', 'api/cron/*')) {
            return $this->setupGate($request, $next);
        }

        if ($request->is('livewire-*/update')) {
            return $next($request);
        }

        // A replayed Livewire snapshot only meets the setup gate: the real request already passed the rest.
        if ($this->marker->isTrusted() || LivewireUpdate::active()) {
            return $this->setupGate($request, $next);
        }

        try {
            DB::connection()->getPdo();
            $isSetUp = Schema::hasTable('users') && User::query()->count() > 0;
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->isApi($request) ? $this->apiError(__('errors.database_unreachable.title'), __('errors.database_unreachable.message')) : SiteUnavailable::response();
        }

        if (!$isSetUp) {
            if ($this->isApi($request)) {
                return $this->apiError(__('errors.not_installed.title'), __('errors.not_installed.message'));
            }

            return response()->view('system.errors.database_error', [
                'title'   => __('errors.not_installed.title'),
                'message' => __('errors.not_installed.message'),
                'link'    => [
                    'url'  => '/admin',
                    'text' => __('errors.not_installed.link'),
                ],
            ], 503);
        }

        // Sites installed before the marker existed get it on their first request.
        if (!$this->marker->exists()) {
            $this->marker->write();
        }

        return $this->setupGate($request, $next);
    }

    private function setupGate(Request $request, Closure $next): Response
    {
        // The setup flag is a cached setting, so this costs no query once warm. The API has no setup page.
        if ($this->isApi($request) || setting('general.setup_completed', true)) {
            return $next($request);
        }

        // Panel pages have their own gates; the Livewire route is gated through its snapshot's page.
        // Plain web routes under admin/ (route-forge API, page preview) are not panel pages.
        $name = $request->route()?->getName();

        if ((is_string($name) && str_starts_with($name, 'filament.')) || $request->is('livewire-*/update') || AdminGate::onlyExemptComponents()) {
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

    private function isApi(Request $request): bool
    {
        return $request->is('api/*');
    }

    private function apiError(string $title, string $message): JsonResponse
    {
        return response()->json([
            'title'   => $title,
            'details' => $message,
            'status'  => 503,
            'error'   => ['status' => 503, 'message' => $message],
        ], 503, ['content-type' => 'application/problem+json']);
    }
}
