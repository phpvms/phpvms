<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum PostUpgradeOutcome
{
    // Every task is done.
    case Done;
    // Another process holds the lock.
    case Busy;
}
