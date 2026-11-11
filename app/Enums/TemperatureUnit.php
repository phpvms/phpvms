<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Units for temperatures, as `units.temperature` stores them.
 */
enum TemperatureUnit: string implements HasLabel
{
    case FAHRENHEIT = 'F';
    case CELSIUS = 'C';

    public function getLabel(): string
    {
        return match ($this) {
            self::FAHRENHEIT => 'Fahrenheit',
            self::CELSIUS    => 'Celsius',
        };
    }
}
