<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum FuelType: int implements HasLabel
{
    use HasSelect;

    case LOW_LEAD = 0;
    case JET_A = 1;
    case MOGAS = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW_LEAD => '100LL',
            self::JET_A    => 'JET A',
            self::MOGAS    => 'MOGAS',
        };
    }
}
