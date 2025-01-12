<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class ExpenseType extends Enum
{
    public const FLIGHT = 'F';

    public const DAILY = 'D';

    public const MONTHLY = 'M';

    protected static array $labels = [
        self::FLIGHT  => 'expenses.type.flight',
        self::DAILY   => 'expenses.type.daily',
        self::MONTHLY => 'expenses.type.monthly',
    ];

    protected static array $codes = [
        self::FLIGHT  => 'F',
        self::DAILY   => 'D',
        self::MONTHLY => 'M',
    ];
}
