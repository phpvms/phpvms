<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum PirepSource: int implements HasLabel
{
    use HasSelect;

    case MANUAL = 0;
    case ACARS = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::MANUAL => __('pireps.source_types.manual'),
            self::ACARS  => __('pireps.source_types.acars'),
        };
    }
}
