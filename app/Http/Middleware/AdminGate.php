<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\LivewireUpdate;
use App\Features\Setup\Support\RequestSiteState;
use App\Filament\Pages\SetupWizard;
use App\Filament\Pages\SystemUpdates;
use App\Filament\Setup\CreateFirstUser;
use App\Filament\Setup\WebInstaller;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Throwable;

/**
 * Sends a logged-in admin to the update page while core setup work is pending, then to the
 * setup wizard until setup is complete. Guests still get the login page, so nothing is queued for them.
 * Registered after Filament's auth and as Livewire persistent middleware, so updates from a page
 * that was already open are gated too.
 */
class AdminGate implements Middleware
{
    /**
     * Livewire components that stay reachable while setup is incomplete.
     *
     * @var list<class-string>
     */
    public const array EXEMPT_COMPONENTS = [
        SetupWizard::class,
        WebInstaller::class,
        CreateFirstUser::class,
        SystemUpdates::class,
    ];

    public function __construct(private readonly RequestSiteState $state) {}

    public function handle(Request $request, Closure $next)
    {
        $updatePending = $this->state->of($request) === SiteState::UpdatePending;

        if (!$updatePending && setting('general.setup_completed', true)) {
            return $next($request);
        }

        $user = Filament::auth()->user();

        if (!$user instanceof User || !$user->hasAdminAccess()) {
            return $next($request);
        }

        $panel = Filament::getPanel('admin');

        if ($updatePending && !$request->routeIs(SystemUpdates::getRouteName($panel), $panel->generateRouteName('auth.logout'))) {
            return redirect(SystemUpdates::getUrl(panel: 'admin'));
        }

        if (!setting('general.setup_completed', true) && !self::isExempt($request)) {
            return redirect(SetupWizard::getUrl(panel: 'admin'));
        }

        return $next($request);
    }

    public static function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();

        $panel = Filament::getPanel('admin');

        // The update page stays reachable, or the update redirect and the wizard redirect would loop.
        if (in_array($name, [SetupWizard::getRouteName($panel), SystemUpdates::getRouteName($panel), $panel->generateRouteName('auth.logout')], true)) {
            return true;
        }

        return self::onlyExemptComponents();
    }

    /**
     * Whether this is a Livewire update whose every component is exempt. A plain
     * request's `components` input is ignored, and one outside component sinks the batch.
     */
    public static function onlyExemptComponents(): bool
    {
        if (!LivewireUpdate::active()) {
            return false;
        }

        // The real request: the fake one Livewire runs persistent middleware on has no JSON body.
        $components = (array) request()->input('components', []);

        if ($components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $name = $snapshot['memo']['name'] ?? null;

            if (!is_string($name)) {
                return false;
            }

            try {
                $class = app('livewire.factory')->resolveComponentClass($name);
            } catch (Throwable) {
                return false;
            }

            if (!in_array($class, self::EXEMPT_COMPONENTS, true)) {
                return false;
            }
        }

        return true;
    }
}
