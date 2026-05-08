<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum AirframeSource: int implements HasLabel
{
    use HasSelect;

    case INTERNAL = 0;
    case SIMBRIEF = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::INTERNAL => 'Custom',
            self::SIMBRIEF => 'SimBrief',
        };
    }
}
