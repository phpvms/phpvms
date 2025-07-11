<?php

/**
 * MIT License
 * Copyright (c) 2017 Anh Vũ Đỗ
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Support;

use DateTimeZone;
use Illuminate\Support\Facades\Log;

/**
 * This library is from:
 * https://github.com/JackieDo/Timezone-List
 *
 * With some changes to suite the formatting, etc that we need.
 * Also changed it to provide all static methods instead of through a facade
 */
class Timezonelist
{
    /**
     * Whitespace seperate
     */
    const WHITESPACE_SEP = '&nbsp;&nbsp;&nbsp;&nbsp;';

    /**
     * Popular timezones
     *
     * @var array
     */
    protected static $popularTimezones = [
        'GMT' => 'GMT timezone',
        'UTC' => 'UTC timezone',
    ];

    /**
     * All continents of the world
     *
     * @var array
     */
    protected static $continents = [
        'Africa'     => DateTimeZone::AFRICA,
        'America'    => DateTimeZone::AMERICA,
        'Antarctica' => DateTimeZone::ANTARCTICA,
        'Arctic'     => DateTimeZone::ARCTIC,
        'Asia'       => DateTimeZone::ASIA,
        'Atlantic'   => DateTimeZone::ATLANTIC,
        'Australia'  => DateTimeZone::AUSTRALIA,
        'Europe'     => DateTimeZone::EUROPE,
        'Indian'     => DateTimeZone::INDIAN,
        'Pacific'    => DateTimeZone::PACIFIC,
    ];

    /**
     * Format to display timezones
     *
     * @param  string $timezone
     * @param  string $continent
     * @param  bool   $htmlencode
     * @return string
     */
    public static function formatTimezone($timezone, $continent, $htmlencode = true)
    {
        try {
            $time = new \DateTimeImmutable('now', new DateTimeZone($timezone));
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return '';
        }

        $offset = $time->format('P');
        if ($htmlencode) {
            $offset = str_replace(['-', '+'], [' &minus; ', ' &plus; '], $offset);
        }
        $timezone = substr($timezone, \strlen($continent) + 1);
        $timezone = str_replace(['St_', '_'], ['St. ', ' '], $timezone);

        return '(GMT/UTC'.$offset.')'.self::WHITESPACE_SEP.$timezone;
    }

    /**
     * Create a GMT timezone select element for form
     *
     * @param string $selected
     * @param mixed  $attr
     * @param bool   $htmlencode
     *
     * @throws \Exception
     */
    public static function create(string $name, $selected = '', ?string $attr = '', $htmlencode = true): string
    {
        // Attributes for select element
        $attrSet = '';
        if ($attr !== null && $attr !== '' && $attr !== '0') {
            if (\is_array($attr)) {
                foreach ($attr as $attr_name => $attr_value) {
                    $attrSet .= ' '.$attr_name.'="'.$attr_value.'"';
                }
            } else {
                $attrSet = ' '.$attr;
            }
        }
        // start select element
        $listbox = '<select name="'.$name.'"'.$attrSet.'>';
        // Add popular timezones
        $listbox .= '<optgroup label="General">';
        foreach (self::$popularTimezones as $key => $value) {
            $selected_attr = ($selected === $key) ? ' selected="selected"' : '';
            $listbox .= '<option value="'.$key.'" '.$selected_attr.'>'.$value.'</option>';
        }
        $listbox .= '</optgroup>';
        // Add all timezone of continents
        foreach (self::$continents as $continent => $mask) {
            $timezones = DateTimeZone::listIdentifiers($mask);
            // start optgroup tag
            $listbox .= '<optgroup label="'.$continent.'">';
            // create option tags
            foreach ($timezones as $timezone) {
                $selected_attr = ($selected === $timezone) ? ' selected="selected"' : '';
                $listbox .= '<option value="'.$timezone.'"'.$selected_attr.'>';
                $listbox .= static::formatTimezone($timezone, $continent, $htmlencode);
                $listbox .= '</option>';
            }
            // end optgroup tag
            $listbox .= '</optgroup>';
        }
        // end select element
        $listbox .= '</select>';

        return $listbox;
    }

    /**
     * Create a timezone array
     *
     * @param  bool              $htmlencode
     * @return non-empty-array[]
     */
    public static function toArray($htmlencode = false): array
    {
        $list = [];
        // Add popular timezones to list
        foreach (self::$popularTimezones as $key => $value) {
            $list['General'][$key] = $value;
        }
        // Add all timezone of continents to list
        foreach (self::$continents as $continent => $mask) {
            $timezones = DateTimeZone::listIdentifiers($mask);
            foreach ($timezones as $timezone) {
                $list[$continent][$timezone] = self::formatTimezone($timezone, $continent, $htmlencode);
            }
        }

        return $list;
    }
}
