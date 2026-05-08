<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AircraftState: int implements HasColor, HasLabel
{
    use HasSelect;

    case PARKED = 0;
    case IN_USE = 1;
    case IN_AIR = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::PARKED => 'On Ground',
            self::IN_USE => 'In Use',
            self::IN_AIR => 'In Air',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PARKED => 'gray',
            self::IN_USE => 'warning',
            self::IN_AIR => 'success',
        };
    }
}
