<?php

declare(strict_types=1);

namespace Modules\AddonManager\Providers;

use App\Contracts\Addons\AddonServiceProvider;
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
class AddonManagerServiceProvider extends AddonServiceProvider
{
    /**
     * No stylesheet is registered here. The Addons page's classes live in
     * resources/css/filament/admin/theme.css, because that is the only file
     * with a Tailwind build — its `@source` globs do not reach modules/, so
     * anything written here could never compile.
     *
     * The catalog is fetched lazily when the Addons page is opened and kept
     * fresh by the scheduled `addons:check-updates` command — never on an
     * unrelated admin render, so a slow registry can't delay other pages.
     */
    #[Override]
    public function registerSchedule(Schedule $schedule): void
    {
        $event = $schedule->command('addons:check-updates')->withoutOverlapping();

        match ((string) config('addon-manager.update_check_cadence', 'daily')) {
            'hourly'    => $event->hourly(),
            'sixhourly' => $event->cron('0 */6 * * *'),
            default     => $event->daily(),
        };
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
