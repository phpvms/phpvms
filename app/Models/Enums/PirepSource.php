<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class PirepSource extends Enum
{
    public const MANUAL = 0;

    public const ACARS = 1;

    protected static array $labels = [
        self::MANUAL => 'pireps.source_types.manual',
        self::ACARS  => 'pireps.source_types.acars',
    ];
}
