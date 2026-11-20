<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Pages\SystemUpdates;
use App\Filament\System\Installer;
use App\Filament\System\Updater;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;

/**
 * Keeps the update page reachable while setup has the site down.
 */
final class PreventRequestsDuringSetup extends PreventRequestsDuringMaintenance
{
    // A string so setup does not depend on the page class existing.
    private const string SYSTEM_UPDATES_PAGE = SystemUpdates::class;

    /**
     * @param Request $request
     */
    protected function inExceptArray($request): bool
    {
        // api/cron/* is the secret-checked web cron, which must keep running setup while down.
        if (parent::inExceptArray($request) || $request->is('system/*', 'livewire-*/livewire*.js', 'api/cron/*')) {
            return true;
        }

        return $request->is('livewire-*/update') && $this->onlySetupComponents($request);
    }

    private function onlySetupComponents(mixed $request): bool
    {
        $components = $request->input('components');
        if (!is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = is_array($component) && is_string($component['snapshot'] ?? null)
                ? json_decode($component['snapshot'], true)
                : null;
            $name = is_array($snapshot) ? ($snapshot['memo']['name'] ?? null) : null;

            if (!in_array($name, [Installer::class, Updater::class, self::SYSTEM_UPDATES_PAGE], true)) {
                return false;
            }
        }

        return true;
    }
}
