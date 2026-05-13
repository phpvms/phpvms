<?php

namespace App\Support;

class Days
{
    public const int MONDAY = 1 << 0;

    public const int TUESDAY = 1 << 1;

    public const int WEDNESDAY = 1 << 2;

    public const int THURSDAY = 1 << 3;

    public const int FRIDAY = 1 << 4;

    public const int SATURDAY = 1 << 5;

    public const int SUNDAY = 1 << 6;

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
     * Map the ISO8601 numeric today to day
     */
    public static array $isoDayMap = [
        1 => self::MONDAY,
        2 => self::TUESDAY,
        3 => self::WEDNESDAY,
        4 => self::THURSDAY,
        5 => self::FRIDAY,
        6 => self::SATURDAY,
        7 => self::SUNDAY,
    ];

    public static function labels(): array
    {
        return collect(static::$labels)
            ->mapWithKeys(fn ($label, $value): array => [$value => __($label)])
            ->toArray();
    }

    public static function label(int $value): ?string
    {
        return __(static::$labels[$value] ?? (string) $value);
    }

    public static function getDaysMask(array $days): int
    {
        $mask = 0;
        foreach ($days as $day) {
            $mask |= (int) $day;
        }

        return $mask;
    }

    public static function in(int|array $mask, int $day): bool
    {
        if (is_array($mask)) {
            return false;
        }

        return ($mask & $day) === $day;
    }

    public static function isToday(int $val): bool
    {
        $today = (int) date('N'); // 1 (Monday) to 7 (Sunday)
        $map = [
            1 => self::MONDAY,
            2 => self::TUESDAY,
            3 => self::WEDNESDAY,
            4 => self::THURSDAY,
            5 => self::FRIDAY,
            6 => self::SATURDAY,
            7 => self::SUNDAY,
        ];

        return self::in($val, $map[$today]);
    }
}
