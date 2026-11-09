<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Features\Themes\ThemeOutput;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The active theme's head output: its CSS and head fields and the enabled
 * global slot rows (see {@see ThemeOutput}). Place after the theme's stylesheet.
 *
 *     <x-theme-head />
 */
class ThemeHead extends Component
{
    public function render(): View
    {
        return view('components.theme-head', [
            'output' => app(ThemeOutput::class)->head(Theme::get()),
        ]);
    }
}
