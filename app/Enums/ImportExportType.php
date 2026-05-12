<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum ImportExportType: int implements HasLabel
{
    use HasSelect;

    case AIRLINE = 1;
    case AIRCRAFT = 2;
    case AIRPORT = 3;
    case EXPENSES = 4;
    case FARES = 5;
    case FLIGHTS = 6;
    case SUBFLEETS = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::AIRLINE   => 'airline',
            self::AIRCRAFT  => 'aircraft',
            self::AIRPORT   => 'airport',
            self::EXPENSES  => 'expense',
            self::FARES     => 'fare',
            self::FLIGHTS   => 'flight',
            self::SUBFLEETS => 'subfleet',
        };
    }
}
