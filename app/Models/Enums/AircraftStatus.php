<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class AircraftStatus extends Enum
{
    public const ACTIVE = 'A';

    public const MAINTENANCE = 'M';

    public const STORED = 'S';

    public const RETIRED = 'R';

    public const SCRAPPED = 'C';

    public const WRITTEN_OFF = 'W';

    public static array $labels = [
        self::ACTIVE      => 'aircraft.status.active',
        self::MAINTENANCE => 'aircraft.status.maintenance',
        self::STORED      => 'aircraft.status.stored',
        self::RETIRED     => 'aircraft.status.retired',
        self::SCRAPPED    => 'aircraft.status.scrapped',
        self::WRITTEN_OFF => 'aircraft.status.written',
    ];
}
