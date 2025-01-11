<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class FuelType extends Enum
{
    public const LOW_LEAD = 0;

    public const JET_A = 1;

    public const MOGAS = 2;

    public static array $labels = [
        self::LOW_LEAD => '100LL',
        self::JET_A    => 'JET A',
        self::MOGAS    => 'MOGAS',
    ];
}
