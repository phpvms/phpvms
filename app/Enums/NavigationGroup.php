<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    use HasSelect;

    case Operations = 'Operations';
    case Planning = 'Planning';
    case Fleet = 'Fleet';
    case Pilots = 'Pilots';
    case Finance = 'Finance';
    case Config = 'Config';
    case AddOns = 'Add-Ons';
    case System = 'System';

    public function getLabel(): string
    {
        return match ($this) {
            self::Config     => __('filament.config'),
            self::Operations => __('filament.operations'),
            self::Planning   => __('filament.planning'),
            self::Fleet      => __('filament.fleet'),
            self::Pilots     => __('filament.pilots'),
            self::Finance    => __('filament.finance'),
            self::AddOns     => __('filament.addons'),
            self::System     => __('filament.system'),
        };
    }
}
