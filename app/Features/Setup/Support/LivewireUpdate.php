<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * Livewire only aborts a persistent-middleware chain on a redirect, so a gate
 * that answers with a page must abort() instead when a Livewire update is running it.
 */
final class LivewireUpdate
{
    /**
     * Whether the real request is a Livewire update, even when the middleware
     * runs on the fake request Livewire builds for the page it came from.
     */
    public static function active(): bool
    {
        return app(HandleRequests::class)->isLivewireRoute();
    }
}
