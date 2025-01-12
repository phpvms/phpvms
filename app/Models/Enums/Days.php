<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

/**
 * Start on Monday - ISO8601.
 */
class Days extends Enum
{
    public const MONDAY = 1 << 0;

    public const TUESDAY = 1 << 1;

    public const WEDNESDAY = 1 << 2;

    public const THURSDAY = 1 << 3;

    public const FRIDAY = 1 << 4;

    public const SATURDAY = 1 << 5;

    public const SUNDAY = 1 << 6;

    public static array $labels = [
        self::MONDAY    => 'common.days.mon',
        self::TUESDAY   => 'common.days.tues',
        self::WEDNESDAY => 'common.days.wed',
        self::THURSDAY  => 'common.days.thurs',
        self::FRIDAY    => 'common.days.fri',
        self::SATURDAY  => 'common.days.sat',
        self::SUNDAY    => 'common.days.sun',
    ];

    public static array $codes = [
        'M'  => self::MONDAY,
        'T'  => self::TUESDAY,
        'W'  => self::WEDNESDAY,
        'Th' => self::THURSDAY,
        'F'  => self::FRIDAY,
        'S'  => self::SATURDAY,
        'Su' => self::SUNDAY,
    ];

    /**
     * Map the ISO8601 numeric today to day.
     */
    public static $isoDayMap = [
        1 => self::MONDAY,
        2 => self::TUESDAY,
        3 => self::WEDNESDAY,
        4 => self::THURSDAY,
        5 => self::FRIDAY,
        6 => self::SATURDAY,
        7 => self::SUNDAY,
    ];

    /**
     * Create the masked value for the days of week.
     *
     *
     * @return int|mixed
     */
    public static function getDaysMask(array $days)
    {
        $mask = 0;
        foreach ($days as $day) {
            $mask |= $day;
        }

        return $mask;
    }

    /**
     * See if the given mask has a day.
     */
    public static function in($mask, $day): bool
    {
        return in_mask($mask, $day);
    }

    /**
     * Does the mask contain today?
     */
    public static function isToday($val): bool
    {
        return static::in($val, static::$isoDayMap[(int) date('N')]);
    }
}
