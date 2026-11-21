<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
