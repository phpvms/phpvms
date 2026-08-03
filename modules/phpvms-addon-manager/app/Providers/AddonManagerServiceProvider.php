<?php

declare(strict_types=1);

namespace Modules\AddonManager\Providers;

use App\Contracts\Modules\ServiceProvider;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Console\Scheduling\Schedule;
use Modules\AddonManager\Services\RegistryClient;
use Override;
use Throwable;

/**
 * Service provider for the bundled Addon Manager module.
 *
 * The base contract auto-wires config/, routes/, views/, database/migrations,
 * translations, and app/Console/Commands. The module's Filament Addons page is
 * discovered into the core admin panel by AdminPanelProvider (main-panel module
 * discovery), so this module ships no PanelProvider of its own.
 */
class AddonManagerServiceProvider extends ServiceProvider
{
    #[Override]
    public function boot(): void
    {
        parent::boot();

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $event = $schedule->command('addons:check-updates')->withoutOverlapping();

            match ((string) config('addon-manager.update_check_cadence', 'daily')) {
                'hourly'    => $event->hourly(),
                'sixhourly' => $event->cron('0 */6 * * *'),
                default     => $event->daily(),
            };
        });

        // Warm the catalog cache on admin-panel entry so the Addons page and its
        // update badge render without a first-hit fetch. RegistryClient::catalog()
        // self-limits to one refresh per TTL and serves stale on failure, so this
        // is a cache read on all but the first (or post-expiry) admin request.
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_START, function (): string {
            if (Filament::getCurrentPanel()?->getId() === 'admin') {
                try {
                    app(RegistryClient::class)->catalog();
                } catch (Throwable) {
                    // Never let catalog warming break an admin page render.
                }
            }

            return '';
        });
    }

    /**
     * Short identifier for the view/translation namespace: `addon-manager::…`.
     */
    #[Override]
    protected function addonNamespace(): string
    {
        return 'addon-manager';
    }
}
