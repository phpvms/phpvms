<?php

declare(strict_types=1);

namespace App\Features\Shell;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the shell slice's Livewire components as `<livewire:shell::name />`
 * and binds the shell data once per request.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class ShellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not a singleton: it holds the request's user and flash, and
        // a long-running Octane worker must build a fresh one per request.
        $this->app->scoped(ShellData::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace('shell', classNamespace: 'App\\Features\\Shell\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
