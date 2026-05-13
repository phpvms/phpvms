<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AircraftStatus: string implements HasColor, HasLabel
{
    use HasSelect;

    case ACTIVE = 'A';
    case MAINTENANCE = 'M';
    case STORED = 'S';
    case RETIRED = 'R';
    case SCRAPPED = 'C';
    case WRITTEN_OFF = 'W';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE      => __('aircraft.status.active'),
            self::MAINTENANCE => __('aircraft.status.maintenance'),
            self::STORED      => __('aircraft.status.stored'),
            self::RETIRED     => __('aircraft.status.retired'),
            self::SCRAPPED    => __('aircraft.status.scrapped'),
            self::WRITTEN_OFF => __('aircraft.status.written'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE      => 'success',
            self::MAINTENANCE => 'warning',
            self::STORED      => 'gray',
            self::RETIRED     => 'danger',
            self::SCRAPPED    => 'danger',
            self::WRITTEN_OFF => 'danger',
        };
    }
}
