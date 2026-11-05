<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Http\Controllers;

use App\Services\AirportService;
use App\Support\Metar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PhpvmsDashboard\Http\Data\WeatherData;
use Modules\PhpvmsDashboard\Support\WeatherSummary;
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

        if ($icao === '' || !preg_match('/^[A-Z0-9]{3,4}$/', $icao)) {
            return response()->json([
                'error'   => true,
                'message' => 'Invalid ICAO code.',
                'icao'    => $icao,
            ], 400);
        }

        try {
            $metar = $this->airportService->getMetar($icao);
            $taf = $this->airportService->getTaf($icao);
        } catch (Throwable $throwable) {
            return response()->json([
                'error'   => true,
                'message' => 'Weather provider error: '.$throwable->getMessage(),
                'icao'    => $icao,
            ], 503);
        }

        if (!$metar instanceof Metar && !$taf instanceof Metar) {
            return response()->json([
                'error'   => true,
                'message' => 'No weather data available for '.$icao.'. The weather provider may be unavailable or the ICAO code may not have a reporting station.',
                'icao'    => $icao,
            ], 503);
        }

        return WeatherSummary::from($icao, $metar);
    }
}
