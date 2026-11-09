<?php

declare(strict_types=1);

namespace App\Features\Pireps;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the pireps slice's Livewire components as `<livewire:pireps::name />`.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class PirepsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('pireps', classNamespace: 'App\\Features\\Pireps\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
