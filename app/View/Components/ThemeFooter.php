<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Features\Themes\ThemeOutput;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The active theme's footer output: its footer fields and the enabled global
 * slot rows (see {@see ThemeOutput}). Place just before `</body>`.
 *
 *     <x-theme-footer />
 */
class ThemeFooter extends Component
{
    public function render(): View
    {
        return view('components.theme-footer', [
            'output' => app(ThemeOutput::class)->footer(Theme::get()),
        ]);
    }
}
