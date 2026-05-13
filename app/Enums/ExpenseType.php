<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum ExpenseType: string implements HasLabel
{
    use HasSelect;

    case FLIGHT = 'F';
    case DAILY = 'D';
    case MONTHLY = 'M';

    public function getLabel(): string
    {
        return match ($this) {
            self::FLIGHT  => __('expenses.type.flight'),
            self::DAILY   => __('expenses.type.daily'),
            self::MONTHLY => __('expenses.type.monthly'),
        };
    }
}
