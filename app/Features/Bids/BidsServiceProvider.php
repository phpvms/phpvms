<?php

declare(strict_types=1);

namespace App\Features\Bids;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the bids slice's Livewire components as `<livewire:bids::name />`.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class BidsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('bids', classNamespace: 'App\\Features\\Bids\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
