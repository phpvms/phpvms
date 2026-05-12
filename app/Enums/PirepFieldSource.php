<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum PirepFieldSource: int implements HasLabel
{
    use HasSelect;

    case MANUAL = 0;
    case ACARS = 1;
    case BOTH = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::ACARS  => 'Acars',
            self::BOTH   => 'Manual & Acars',
        };
    }
}
