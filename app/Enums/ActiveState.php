<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActiveState: int implements HasColor, HasLabel
{
    use HasSelect;

    case INACTIVE = 0;
    case ACTIVE = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE   => __('common.active'),
            self::INACTIVE => __('common.inactive'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE   => 'success',
            self::INACTIVE => 'gray',
        };
    }
}
