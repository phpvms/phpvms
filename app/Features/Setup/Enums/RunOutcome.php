<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum RunOutcome
{
    case Ran;
    case Busy;
    case NothingRequested;
    case Failed;
}
