<?php

declare(strict_types=1);

namespace App\Features\Themes\Listeners;

use App\Features\Themes\ThemeOutput;

/**
 * Invalidates the compiled theme output after a ThemeSetting row is saved or
 * deleted through Eloquent.
 */
final class BumpThemeOutput
{
    public function handle(): void
    {
        ThemeOutput::bump();
    }
}
