<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class FareType extends Enum
{
    public const PASSENGER = 0;

    public const CARGO = 1;

    public static array $labels = [
        self::PASSENGER => 'Passenger',
        self::CARGO     => 'Cargo',
    ];
}
