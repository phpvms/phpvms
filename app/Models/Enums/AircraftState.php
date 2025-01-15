<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class AircraftState extends Enum
{
    public const PARKED = 0;

    public const IN_USE = 1;

    public const IN_AIR = 2;

    public static array $labels = [
        self::PARKED => 'On Ground',
        self::IN_USE => 'In Use',
        self::IN_AIR => 'In Air',
    ];
}
