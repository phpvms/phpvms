<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

/**
 * Class NavaidType
 * Types based on/compatible with OpenFMC
 * https://github.com/skiselkov/openfmc/blob/master/airac.h.
 */
class NavaidType extends Enum
{
    public const VOR = 1 << 0;

    public const VOR_DME = 1 << 1;

    public const LOC = 1 << 4;

    public const LOC_DME = 1 << 5;

    public const NDB = 1 << 6;

    public const TACAN = 1 << 7;

    public const UNKNOWN = 1 << 8;

    public const INNER_MARKER = 1 << 9;

    public const OUTER_MARKER = 1 << 10;

    public const FIX = 1 << 11;

    public const ANY_VOR = self::VOR | self::VOR_DME;

    public const ANY_LOC = self::LOC | self::LOC_DME;

    public const ANY = (self::UNKNOWN << 1) - 1;

    /**
     * Names and titles.
     */
    public static array $labels = [
        self::VOR     => 'VOR',
        self::VOR_DME => 'VOR DME',
        self::LOC     => 'Localizer',
        self::LOC_DME => 'Localizer DME',
        self::NDB     => 'Non-directional Beacon',
        self::TACAN   => 'TACAN',
        self::UNKNOWN => 'Unknown',
        self::ANY_VOR => 'VOR',
        self::ANY_LOC => 'Localizer',
    ];

    public static $icons = [
        self::VOR     => 'VOR',
        self::VOR_DME => 'VOR DME',
        self::LOC     => 'Localizer',
        self::LOC_DME => 'Localizer DME',
        self::NDB     => 'Non-directional Beacon',
        self::TACAN   => 'TACAN',
        self::UNKNOWN => 'Unknown',
        self::ANY_VOR => 'VOR',
        self::ANY_LOC => 'Localizer',
    ];
}
