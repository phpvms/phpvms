<?php

declare(strict_types=1);

namespace App\Features\Dashboard;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the dashboard slice's Livewire components as `<livewire:dashboard::name />`.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class DashboardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('dashboard', classNamespace: 'App\\Features\\Dashboard\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
