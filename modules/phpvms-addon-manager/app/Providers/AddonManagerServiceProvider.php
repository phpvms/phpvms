<?php

declare(strict_types=1);

namespace Modules\AddonManager\Providers;

use App\Contracts\Modules\ServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Override;

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

        // The Addons page's scoped stylesheet (plain CSS, not a Tailwind build).
        // Published to public/ by `php artisan filament:assets`.
        FilamentAsset::register([
            Css::make('addon-manager-addons', __DIR__.'/../../resources/css/addons.css'),
        ], package: 'phpvms/addon-manager');

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $event = $schedule->command('addons:check-updates')->withoutOverlapping();

            match ((string) config('addon-manager.update_check_cadence', 'daily')) {
                'hourly'    => $event->hourly(),
                'sixhourly' => $event->cron('0 */6 * * *'),
                default     => $event->daily(),
            };
        });

        // The catalog is fetched lazily when the Addons page is opened and kept
        // fresh by the scheduled `addons:check-updates` command — never on an
        // unrelated admin render, so a slow registry can't delay other pages.
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
