<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Units for distances, as `units.distance` stores them.
 */
enum DistanceUnit: string implements HasLabel
{
    case KILOMETERS = 'km';
    case MILES = 'mi';
    case NAUTICAL_MILES = 'nmi';

    public function getLabel(): string
    {
        return match ($this) {
            self::KILOMETERS     => 'kilometers',
            self::MILES          => 'miles',
            self::NAUTICAL_MILES => 'nautical miles',
        };
    }
}
