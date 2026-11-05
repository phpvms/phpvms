<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Support;

use App\Support\Metar;
use Modules\PhpvmsDashboard\Http\Data\WeatherData;
use Throwable;

/**
 * Summarises a parsed METAR into the conditions, temperature and wind rows the
 * weather widgets show. Shared by the Vue widget's endpoint and the Livewire widget.
 */
final class WeatherSummary
{
    /**
     * The summary for a station; every row is null when there is no METAR.
     */
    public static function from(string $icao, ?Metar $metar): WeatherData
    {
        return new WeatherData(
            icao: $icao,
            metar: $metar?->raw ?? null,
            conditions: self::resolveConditions($metar),
            temperature: self::resolveTemperature($metar),
            wind: self::resolveWind($metar),
        );
    }

    /**
     * Extract a human-readable conditions string from a parsed Metar object.
     * The Metar `result` array keys are accessed via ArrayAccess (__get magic).
     */
    private static function resolveConditions(?Metar $metar): ?string
    {
        if (!$metar instanceof Metar) {
            return null;
        }

        try {
            $clouds = $metar->clouds;
            if (is_array($clouds) && $clouds !== []) {
                $first = $clouds[0];
                $cover = $first['cover'] ?? null;
                $base = isset($first['base_feet_agl']) ? (int) $first['base_feet_agl'].'ft' : null;

                return match ($cover) {
                    'CLR', 'SKC', 'NSC', 'NCD' => 'Clear',
                    'FEW'                      => 'Few Clouds'.($base ? ' at '.$base : ''),
                    'SCT'                      => 'Scattered Clouds'.($base ? ' at '.$base : ''),
                    'BKN'                      => 'Broken Clouds'.($base ? ' at '.$base : ''),
                    'OVC'                      => 'Overcast'.($base ? ' at '.$base : ''),
                    default                    => $cover,
                };
            }
        } catch (Throwable) {
            // Non-critical — just return null
        }

        return null;
    }

    /**
     * Extract temperature as a formatted string.
     */
    private static function resolveTemperature(?Metar $metar): ?string
    {
        if (!$metar instanceof Metar) {
            return null;
        }

        try {
            $temp = $metar->temperature;
            if ($temp === null) {
                return null;
            }

            $unit = strtolower((string) setting('units.temperature', 'c'));
            if ($unit === 'f' && isset($temp['fahrenheit'])) {
                return round((float) $temp['fahrenheit']).' °F';
            }

            if (isset($temp['celsius'])) {
                return round((float) $temp['celsius']).' °C';
            }
        } catch (Throwable) {
            // Non-critical
        }

        return null;
    }

    /**
     * Extract wind as a formatted string.
     */
    private static function resolveWind(?Metar $metar): ?string
    {
        if (!$metar instanceof Metar) {
            return null;
        }

        try {
            $dir = $metar->wind_direction;
            $speed = $metar->wind_speed;

            if ($dir === null && $speed === null) {
                return null;
            }

            $dirStr = ($dir !== null) ? $dir.'°' : 'VRB';
            $speedVal = ($speed !== null && is_object($speed) && method_exists($speed, 'toUnit'))
                ? round($speed->toUnit('kt')).' kt'
                : ($speed !== null ? (string) $speed : '—');

            return sprintf('%s at %s', $dirStr, $speedVal);
        } catch (Throwable) {
            // Non-critical
        }

        return null;
    }
}
