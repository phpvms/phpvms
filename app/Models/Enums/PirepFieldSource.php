<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class PirepFieldSource extends Enum
{
    public const MANUAL = 0;

    public const ACARS = 1;

    public const BOTH = 3;

    protected static array $labels = [
        self::MANUAL => 'Manual',
        self::ACARS  => 'Acars',
        self::BOTH   => 'Manual & Acars',
    ];
}
