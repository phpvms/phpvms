<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum FareType: int implements HasLabel
{
    use HasSelect;

    case PASSENGER = 0;
    case CARGO = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::PASSENGER => 'Passenger',
            self::CARGO     => 'Cargo',
        };
    }
}
