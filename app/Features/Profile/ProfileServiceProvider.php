<?php

declare(strict_types=1);

namespace App\Features\Profile;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the profile slice's Livewire components as `<livewire:profile::name />`.
 *
 * The classes are core; each component names its view explicitly, so the
 * markup resolves through the active theme.
 */
class ProfileServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('profile', classNamespace: 'App\\Features\\Profile\\Livewire', classPath: __DIR__.'/Livewire');
    }
}
