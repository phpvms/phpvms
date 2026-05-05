<?php

declare(strict_types=1);

namespace App\Models\Enums;

use App\Contracts\Enum;

class AcarsType extends Enum
{
    public const FLIGHT_PATH = 0;

    public const ROUTE = 1;

    public const LOG = 2;
}
