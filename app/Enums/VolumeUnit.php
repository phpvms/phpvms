<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Units for volumes, as `units.volume` stores them.
 */
enum VolumeUnit: string implements HasLabel
{
    case GALLONS = 'gallons';
    case LITERS = 'l';

    public function getLabel(): string
    {
        return match ($this) {
            self::GALLONS => 'gallons',
            self::LITERS  => 'liters',
        };
    }
}
