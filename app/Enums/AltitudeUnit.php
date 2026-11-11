<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Units for altitudes, as `units.altitude` stores them.
 */
enum AltitudeUnit: string implements HasLabel
{
    case FEET = 'ft';
    case METERS = 'm';

    public function getLabel(): string
    {
        return match ($this) {
            self::FEET   => 'feet',
            self::METERS => 'meters',
        };
    }
}
