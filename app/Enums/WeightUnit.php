<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Units for weights, as `units.weight` and `units.fuel` store them.
 */
enum WeightUnit: string implements HasLabel
{
    case POUNDS = 'lbs';
    case KILOGRAMS = 'kg';

    public function getLabel(): string
    {
        return match ($this) {
            self::POUNDS    => 'lbs',
            self::KILOGRAMS => 'kg',
        };
    }
}
