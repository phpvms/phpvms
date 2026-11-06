<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of row on a flight briefing timeline.
 */
enum BriefingItemKind: string
{
    case Station = 'station';
    case Route = 'route';
    case Fix = 'fix';
    case Phase = 'phase';
    case Notice = 'notice';
}
