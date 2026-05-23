<?php

declare(strict_types=1);

namespace App\Support;

use DateTime;

/**
 * Parse free-form flight time strings into H:i:s format.
 */
class FlightTimeParser
{
    /**
     * Parse a free-form time input into an H:i:s string.
     *
     * Supported formats: Hi, H:i, H:i:s, G:i, h:i A, h:i a, h A, h a, G, H.
     * Trailing timezone abbreviations (2-4 letters) and Z/L suffixes are stripped before parsing.
     * Returns null for empty or unrecognised input.
     */
    public static function parse(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = trim($input);

        if ($input === '') {
            return null;
        }

        $input = preg_replace('/\s+(?!(?:AM|PM)$)[A-Za-z]{2,4}$/i', '', $input);
        $input = rtrim((string) $input, 'ZzLl');

        if ($input === '') {
            return null;
        }

        $formats = [
            'h:i A', 'h:i a',
            'g:i A', 'g:i a',
            'h A', 'h a',
            'g A', 'g a',
            'H:i:s',
            'H:i', 'G:i',
            'Hi',
            'H', 'G',
        ];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat('!'.$format, $input);
            if ($dt !== false) {
                // PHP wraps overflow hours for several formats (e.g. 25:00 → 01:00 next day).
                // Validate numeric-only formats explicitly.
                if ($format === 'H:i:s' && preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $input, $m) && ((int) $m[1] > 23 || (int) $m[2] > 59 || (int) $m[3] > 59)) {
                    continue;
                }

                if ($format === 'H:i' && preg_match('/^(\d{2}):(\d{2})$/', $input, $m) && ((int) $m[1] > 23 || (int) $m[2] > 59)) {
                    continue;
                }

                if ($format === 'G:i' && preg_match('/^(\d{1,2}):(\d{2})$/', $input, $m) && ((int) $m[1] > 23 || (int) $m[2] > 59)) {
                    continue;
                }

                if ($format === 'Hi') {
                    $hour = (int) substr($input, 0, 2);
                    $minute = (int) substr($input, 2, 2);
                    if ($hour > 23) {
                        continue;
                    }

                    if ($minute > 59) {
                        continue;
                    }
                }

                if ($format === 'H' && preg_match('/^\d{2}$/', $input) && (int) $input > 23) {
                    continue;
                }

                if ($format === 'G' && preg_match('/^\d{1,2}$/', $input) && (int) $input > 23) {
                    continue;
                }

                return $dt->format('H:i:s');
            }
        }

        return null;
    }
}
