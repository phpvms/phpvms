<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum NavaidType: int implements HasLabel
{
    use HasSelect;

    case VOR = 1;
    case VOR_DME = 2;
    case LOC = 16;
    case LOC_DME = 32;
    case NDB = 64;
    case TACAN = 128;
    case UNKNOWN = 256;
    case INNER_MARKER = 512;
    case OUTER_MARKER = 1024;
    case FIX = 2048;

    public function getLabel(): string
    {
        return match ($this) {
            self::VOR          => 'VOR',
            self::VOR_DME      => 'VOR DME',
            self::LOC          => 'Localizer',
            self::LOC_DME      => 'Localizer DME',
            self::NDB          => 'Non-directional Beacon',
            self::TACAN        => 'TACAN',
            self::UNKNOWN      => 'Unknown',
            self::INNER_MARKER => 'Inner Marker',
            self::OUTER_MARKER => 'Outer Marker',
            self::FIX          => 'Fix',
        };
    }
}
