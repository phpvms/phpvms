<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    use HasSelect;

    case Operations = 'Operations';
    case Config = 'Config';
    case AddOns = 'Add-Ons';
    case Developers = 'Developers';

    public function getLabel(): string
    {
        return match ($this) {
            self::Config     => __('filament.config'),
            self::Operations => __('filament.operations'),
            self::AddOns     => __('filament.addons'),
            self::Developers => __('filament.developers'),
        };
    }
}
