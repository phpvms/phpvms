<?php

declare(strict_types=1);

namespace App\Features\Ofp;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the ofp slice's Livewire components as `<livewire:ofp::name />`.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class OfpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('ofp', classNamespace: 'App\\Features\\Ofp\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
