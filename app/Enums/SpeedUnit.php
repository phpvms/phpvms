<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Units for speeds, as `units.speed` stores them.
 */
enum SpeedUnit: string implements HasLabel
{
    case KILOMETERS_PER_HOUR = 'km/h';
    case KNOTS = 'knot';

    public function getLabel(): string
    {
        return match ($this) {
            self::KILOMETERS_PER_HOUR => 'km/h',
            self::KNOTS               => 'knot',
        };
    }
}
