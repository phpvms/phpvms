<?php

declare(strict_types=1);

namespace App\Features\Flights;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the flights slice's Livewire components as `<livewire:flights::name />`.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class FlightsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('flights', classNamespace: 'App\\Features\\Flights\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
