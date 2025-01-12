<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class AirframeSource extends Enum
{
    public const INTERNAL = 0;

    public const SIMBRIEF = 1;

    public static array $labels = [
        self::INTERNAL => 'Custom',
        self::SIMBRIEF => 'SimBrief',
    ];
}
