<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    use HasSelect;

    case Config = 'Config';
    case Operations = 'Operations';
    case Modules = 'Modules';
    case Developers = 'Developers';

    public function getLabel(): string
    {
        return match ($this) {
            self::Config     => __('filament.config'),
            self::Operations => __('filament.operations'),
            self::Modules    => __('filament.modules'),
            self::Developers => __('filament.developers'),
        };
    }
}
