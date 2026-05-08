<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum PageType: int implements HasLabel
{
    use HasSelect;

    case PAGE = 0;
    case LINK = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::PAGE => 'Page',
            self::LINK => 'Link',
        };
    }
}
