<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BriefingStationRole: string implements HasLabel
{
    case Departure = 'departure';
    case Arrival = 'arrival';
    case Alternate = 'alternate';

    public function getLabel(): string
    {
        return match ($this) {
            self::Departure => __('briefing.role.departure'),
            self::Arrival   => __('briefing.role.arrival'),
            self::Alternate => __('briefing.role.alternate'),
        };
    }
}
