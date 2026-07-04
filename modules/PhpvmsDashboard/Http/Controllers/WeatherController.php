<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Http\Controllers;

use App\Services\AirportService;
use App\Support\Metar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PhpvmsDashboard\Http\Data\WeatherData;
use Throwable;

/**
 * Weather (METAR) data endpoint OWNED by this addon.
 *
 * GET /api/phpvms-dashboard/weather/{icao}
 *
 * The addon ships BOTH its UI (the pre-built Vue weather widget) and the API it
 * talks to. Disable the addon and its ServiceProvider never boots, so this route
 * is never registered — nothing in the host references it.
 *
 * This mirrors the core App\Http\Controllers\Frontend\WeatherController: it
 * delegates to the core App\Services\AirportService (getMetar/getTaf) and shapes
 * the same summarized JSON (conditions / temperature / wind). On success it
 * returns a WeatherData DTO (Responsable → JSON); error conditions return a
 * well-formed error payload with the appropriate HTTP status — the widget must
 * never make the page 500 because of weather.
 */
final class WeatherController extends Controller
{
    public function __construct(
        private readonly AirportService $airportService
    ) {}

    /**
     * Return weather data for a given ICAO code.
     *
     * Any exception from the weather provider is caught and returned as a
     * well-formed error payload.
     */
    public function show(Request $request, string $icao): WeatherData|JsonResponse
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

        return new WeatherData(
            icao: $icao,
            metar: $metar?->raw ?? null,
            conditions: $this->resolveConditions($metar),
            temperature: $this->resolveTemperature($metar),
            wind: $this->resolveWind($metar),
        );
    }

    /**
     * Extract a human-readable conditions string from a parsed Metar object.
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
