<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The kind of row on a flight briefing timeline.
 */
enum BriefingItemKind: string implements HasLabel
{
    case Station = 'station';
    case Route = 'route';
    case Fix = 'fix';
    case Phase = 'phase';
    case Notice = 'notice';

    public function getLabel(): string
    {
        return match ($this) {
            self::Station => __('briefing.kind.station'),
            self::Route   => __('briefing.kind.route'),
            self::Fix     => __('briefing.kind.fix'),
            self::Phase   => __('briefing.kind.phase'),
            self::Notice  => __('briefing.kind.notice'),
        };
    }
}
