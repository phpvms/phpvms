<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class ImportExportType extends Enum
{
    public const AIRLINE = 1;

    public const AIRCRAFT = 2;

    public const AIRPORT = 3;

    public const EXPENSES = 4;

    public const FARES = 5;

    public const FLIGHTS = 6;

    public const SUBFLEETS = 7;

    public static array $labels = [
        self::AIRLINE   => 'airline',
        self::AIRCRAFT  => 'aircraft',
        self::AIRPORT   => 'airport',
        self::EXPENSES  => 'expense',
        self::FARES     => 'fare',
        self::FLIGHTS   => 'flight',
        self::SUBFLEETS => 'subfleet',
    ];
}
