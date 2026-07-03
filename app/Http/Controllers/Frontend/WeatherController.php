<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Services\AirportService;
use App\Support\Metar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * JSON endpoint for the <weather-widget> custom element (Task Group 5).
 *
 * GET /api/weather/{icao}
 *
 * Returns a clean JSON payload with METAR/TAF data from the configured weather
 * provider (AviationWeather by default — see config/phpvms.php `metar_lookup`).
 * The widget pulls this on connect, so the slow external call never blocks the
 * Inertia page's first paint.
 *
 * Success shape:
 * {
 *   "icao": "KJFK",
 *   "metar": "KJFK 021256Z 27010KT ...",
 *   "taf": "TAF KJFK ...",         // or null
 *   "conditions": "Few Clouds",    // or null
 *   "temperature": "22 °C",        // or null
 *   "wind": "270° at 10 kt",       // or null
 *   "units": { "altitude": "ft", "distance": "nm", "temperature": "c" }
 * }
 *
 * Error shape (any non-200 condition):
 * {
 *   "error": true,
 *   "message": "Human-readable reason",
 *   "icao": "KJFK"
 * }
 *
 * HTTP status codes:
 *   200 — success (data present or empty but parse successful)
 *   400 — missing/invalid ICAO (client error)
 *   503 — provider fetch failed or returned no data
 */
class WeatherController extends Controller
{
    public function __construct(
        private readonly AirportService $airportService
    ) {}

    /**
     * Return weather data for a given ICAO code as JSON.
     *
     * Any exception from the weather provider is caught and returned as a
     * well-formed error payload — the page must never 500 because of weather.
     */
    public function show(Request $request, string $icao): JsonResponse
    {
        $icao = strtoupper(trim($icao));

        if ($icao === '' || ! preg_match('/^[A-Z0-9]{3,4}$/', $icao)) {
            return response()->json([
                'error'   => true,
                'message' => 'Invalid ICAO code.',
                'icao'    => $icao,
            ], 400);
        }

        try {
            $metar = $this->airportService->getMetar($icao);
            $taf   = $this->airportService->getTaf($icao);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => true,
                'message' => 'Weather provider error: '.$e->getMessage(),
                'icao'    => $icao,
            ], 503);
        }

        if ($metar === null && $taf === null) {
            return response()->json([
                'error'   => true,
                'message' => 'No weather data available for '.$icao.'. The weather provider may be unavailable or the ICAO code may not have a reporting station.',
                'icao'    => $icao,
            ], 503);
        }

        return response()->json([
            'icao'        => $icao,
            'metar'       => $metar?->raw ?? null,
            'taf'         => $taf?->raw ?? null,
            'conditions'  => $this->resolveConditions($metar),
            'temperature' => $this->resolveTemperature($metar),
            'wind'        => $this->resolveWind($metar),
            'units'       => [
                'altitude'    => setting('units.altitude', 'ft'),
                'distance'    => setting('units.distance', 'nm'),
                'temperature' => setting('units.temperature', 'c'),
            ],
        ]);
    }

    /**
     * Extract human-readable conditions string from a parsed Metar object.
     * The Metar `result` array keys are accessed via ArrayAccess (__get magic).
     */
    private function resolveConditions(?Metar $metar): ?string
    {
        if ($metar === null) {
            return null;
        }

        try {
            $clouds = $metar->clouds;
            if (is_array($clouds) && count($clouds) > 0) {
                $first = $clouds[0];
                $cover = $first['cover'] ?? null;
                $base  = isset($first['base_feet_agl']) ? (int) $first['base_feet_agl'].'ft' : null;

                return match ($cover) {
                    'CLR', 'SKC', 'NSC', 'NCD' => 'Clear',
                    'FEW'                        => 'Few Clouds'.($base ? " at {$base}" : ''),
                    'SCT'                        => 'Scattered Clouds'.($base ? " at {$base}" : ''),
                    'BKN'                        => 'Broken Clouds'.($base ? " at {$base}" : ''),
                    'OVC'                        => 'Overcast'.($base ? " at {$base}" : ''),
                    default                      => $cover,
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
    private function resolveTemperature(?Metar $metar): ?string
    {
        if ($metar === null) {
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
    private function resolveWind(?Metar $metar): ?string
    {
        if ($metar === null) {
            return null;
        }

        try {
            $dir   = $metar->wind_direction;
            $speed = $metar->wind_speed;

            if ($dir === null && $speed === null) {
                return null;
            }

            $dirStr   = ($dir !== null) ? $dir.'°' : 'VRB';
            $speedVal = ($speed !== null && is_object($speed) && method_exists($speed, 'toUnit'))
                ? round($speed->toUnit('kt')).' kt'
                : ($speed !== null ? (string) $speed : '—');

            return "{$dirStr} at {$speedVal}";
        } catch (Throwable) {
            // Non-critical
        }

        return null;
    }
}
