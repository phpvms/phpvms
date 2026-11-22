<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum PostUpgradeStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
}
