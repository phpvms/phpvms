<?php

namespace App\Support;

use Carbon\Carbon;

class Dates
{
    /**
     * Bitwise operator for setting days of week to integer field
     *
     * @param int   $datefield initial datefield
     * @param array $day_enums Array of values from config("enum.days")
     */
    public static function setDays(int $datefield, array $day_enums): int
    {
        foreach ($day_enums as $day) {
            $datefield |= $day;
        }

        return $datefield;
    }

    /**
     * Bit check if a day exists within a integer bitfield
     *
     * @param int $datefield datefield from database
     * @param int $day_enum  Value from config("enum.days")
     */
    public static function hasDay(int $datefield, int $day_enum): bool
    {
        return ($datefield & $day_enum) === $datefield;
    }

    /**
     * Get the list of months, given a start date
     */
    public static function getMonthsList(Carbon $start_date): array
    {
        $months = [];
        $now = date('Y-m');
        $last_month = $start_date;

        do {
            $last_value = $last_month->format('Y-m');
            $months[$last_value] = $last_month->format('Y F');
            $last_month = $last_month->addMonth();
        } while ($last_value !== $now);

        return $months;
    }

    /**
     * Return the start/end dates for a given month/year
     *
     * @param string $month In "YYYY-MM" format
     */
    public static function getMonthBoundary(string $month): array
    {
        [$year, $month] = explode('-', $month);
        $days = static::getDaysInMonth($month, $year);

        return [
            "$year-$month-01",
            "$year-$month-$days",
        ];
    }

    /**
     * Get the number of days in a month
     * https://www.php.net/manual/en/function.cal-days-in-month.php#38666
     *
     * @param int $month
     * @param int $year
     */
    public static function getDaysInMonth($month, $year): int
    {
        $month = (int) $month;
        $year = (int) $year;

        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }
}
