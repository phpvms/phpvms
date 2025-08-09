<?php

namespace App\Models\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Config;

    public function getLabel(): string
    {
        return match ($this) {
            self::Config => 'Config'
        };
    }
}
