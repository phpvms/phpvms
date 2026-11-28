<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum SiteState
{
    case Unavailable;
    case Install;
    case FirstUser;
    case V7Upgrade;
    case UpdatePending;
    case Current;
}
