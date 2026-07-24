<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

/**
 * The simulator a PIREP was flown on. Values mirror the ACARS contract
 * `SimType` proto enum (contract.proto) so the wire value maps straight to
 * this enum. The gaps at 3 and 8 are deliberate (generic MSFS and Replay are
 * not fileable).
 */
enum SimType: int implements HasLabel
{
    use HasSelect;

    case UNSPECIFIED = 0;
    case PREPAR3D = 1;
    case XPLANE = 2;
    case FSX = 4;
    case FS9 = 5;
    case MSFS_2020 = 6;
    case MSFS_2024 = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::UNSPECIFIED => 'Unspecified',
            self::PREPAR3D    => 'Prepar3D',
            self::XPLANE      => 'X-Plane',
            self::FSX         => 'FSX',
            self::FS9         => 'FS2004',
            self::MSFS_2020   => 'MSFS 2020',
            self::MSFS_2024   => 'MSFS 2024',
        };
    }
}
