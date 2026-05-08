<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum JournalType: int implements HasLabel
{
    use HasSelect;

    case AIRLINE = 0;
    case USER = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::AIRLINE => 'Airline',
            self::USER    => 'User',
        };
    }
}
