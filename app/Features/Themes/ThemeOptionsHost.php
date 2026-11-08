<?php

declare(strict_types=1);

namespace App\Features\Themes;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/**
 * Headless Livewire component used purely as a schema container.
 *
 * Filament builds child schemas through `getLivewire()`, whose return type is
 * non-nullable, so a theme's option form cannot be built without a host. This
 * is the smallest thing that satisfies it, so {@see ThemeOptions::defaults()}
 * can read the form with no request behind it. It is never mounted or rendered.
 */
class ThemeOptionsHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;
}
