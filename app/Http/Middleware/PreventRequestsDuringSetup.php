<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Keeps the update page reachable while setup has the site down.
 */
final class PreventRequestsDuringSetup extends PreventRequestsDuringMaintenance
{
    // The setup panel's routes, which stay up while down, along with Livewire updates from their pages.
    private const array SETUP_ROUTES = ['system', 'system/*'];

    /**
     * @param Request $request
     */
    protected function inExceptArray($request): bool
    {
        // api/cron/* is the secret-checked web cron, which must keep running setup while down.
        if (parent::inExceptArray($request) || $request->is('livewire-*/livewire*.js', 'api/cron/*', ...self::SETUP_ROUTES)) {
            return true;
        }

        return $request->is('livewire-*/update') && $this->onlySetupPages($request);
    }

    /**
     * Livewire batches every pending component into one update, e.g. the toast with the progress poll,
     * so this checks the page each was rendered on, which Livewire's checksum then vouches for.
     */
    private function onlySetupPages(Request $request): bool
    {
        $components = $request->input('components');
        if (!is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = is_array($component) && is_string($component['snapshot'] ?? null)
                ? json_decode($component['snapshot'], true)
                : null;
            $path = is_array($snapshot) ? ($snapshot['memo']['path'] ?? null) : null;

            if (!is_string($path) || !Str::is(self::SETUP_ROUTES, trim($path, '/'))) {
                return false;
            }
        }

        return true;
    }
}
