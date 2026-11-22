<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum RunStatus: string
{
    case Requested = 'requested';
    case Running = 'running';
    case Failed = 'failed';
    case Done = 'done';
}
