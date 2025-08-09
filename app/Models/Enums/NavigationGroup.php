<?php

namespace App\Models\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Config;
    case Operations;

    public function getLabel(): string
    {
        return match ($this) {
            self::Config     => 'Config',
            self::Operations => 'Operations',
        };
    }
}
