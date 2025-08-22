<?php

namespace App\Support\Units;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Contracts\Support\Arrayable;

class Time implements Arrayable
{
    public $hours;

    public $minutes;

    public static function init($minutes, $hours): self
    {
        return new self($minutes, $hours);
    }

    /**
     * Pass just minutes to figure out how many hours
     * Or both hours and minutes
     */
    public function __construct($minutes, $hours = null)
    {
        $minutes = (int) $minutes;

        $this->hours = empty($hours) ? floor($minutes / 60) : (int) $hours;

        $this->minutes = $minutes % 60;
    }

    /**
     * Get the total number minutes, adding up the hours
     *
     * @return float|int
     */
    public function getMinutes()
    {
        return ($this->hours * 60) + $this->minutes;
    }

    /**
     * Alias to getMinutes()
     *
     * @alias getMinutes()
     *
     * @return float|int
     */
    public function asInt()
    {
        return $this->getMinutes();
    }

    /**
     * Return a time string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->hours.'h '.$this->minutes.'m';
    }

    /**
     * @return float|int
     */
    public function toObject()
    {
        return $this->getMinutes();
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): float|int|array
    {
        return $this->getMinutes();
    }

    /**
     * @param int $minutes
     */
    public static function minutesToTimeParts($minutes): array
    {
        $hours = floor($minutes / 60);
        $minutes %= 60;

        return ['h' => $hours, 'm' => $minutes];
    }

    public static function minutesToTimeString($minutes): string
    {
        $hm = self::minutesToTimeParts($minutes);

        return $hm['h'].'h '.$hm['m'].'m';
    }

    /**
     * Convert seconds to an array of hours, minutes, seconds
     *
     * @param  int                                 $seconds
     * @return array{'h': int, 'm': int, 's': int}
     *
     * @throws Exception
     */
    public static function secondsToTimeParts($seconds): array
    {
        $dtF = new DateTimeImmutable('@0', new DateTimeZone('UTC'));
        $dtT = new DateTimeImmutable("@$seconds", new DateTimeZone('UTC'));

        $t = $dtF->diff($dtT);

        $retval = [];
        $retval['h'] = (int) $t->format('%h');
        $retval['m'] = (int) $t->format('%i');
        $retval['s'] = (int) $t->format('%s');

        return $retval;
    }

    /**
     * Convert seconds to HH MM format
     *
     * @param int  $seconds
     * @param bool $incl_sec
     *
     * @throws Exception
     */
    public static function secondsToTimeString($seconds, $incl_sec = false): string
    {
        $hms = self::secondsToTimeParts($seconds);
        $format = $hms['h'].'h '.$hms['m'].'m';
        if ($incl_sec) {
            $format .= ' '.$hms['s'].'s';
        }

        return $format;
    }

    /**
     * @return float|int
     */
    public static function minutesToSeconds($minutes)
    {
        return $minutes * 60;
    }

    /**
     * Convert the seconds to minutes and then round it up
     *
     *
     * @return float|int
     */
    public static function secondsToMinutes($seconds)
    {
        return ceil($seconds / 60);
    }

    /**
     * Convert hours to minutes. Pretty complex
     *
     *
     * @return float|int
     */
    public static function minutesToHours($minutes)
    {
        return $minutes / 60;
    }

    /**
     * @param  null      $minutes
     * @return float|int
     */
    public static function hoursToMinutes($hours, $minutes = null)
    {
        $total = (int) $hours * 60;
        if ($minutes) {
            $total += (int) $minutes;
        }

        return $total;
    }
}
