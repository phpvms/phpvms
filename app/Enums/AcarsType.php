<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum AcarsType: int implements HasLabel
{
    use HasSelect;

    case FLIGHT_PATH = 0;
    case ROUTE = 1;
    case LOG = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::FLIGHT_PATH => 'Flight Path',
            self::ROUTE       => 'Route',
            self::LOG         => 'Log',
        };
    }
}
