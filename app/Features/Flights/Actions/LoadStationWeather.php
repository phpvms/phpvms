<?php

declare(strict_types=1);

namespace App\Features\Flights\Actions;

use App\Http\Controllers\Frontend\WeatherController;
use Throwable;

/**
 * Read the METAR for a station, as the view state the weather cards render.
 *
 * Reads through the same controller as `GET /api/weather/{icao}`, so the text
 * and the stale rule match the API. States: missing-station, loaded, stale,
 * missing, error.
 */
final readonly class LoadStationWeather
{
    /**
     * @return array{state: string, metar: ?string, observedAt: ?string}
     */
    public function handle(?string $icao): array
    {
        if ($icao === null) {
            return ['state' => 'missing-station', 'metar' => null, 'observedAt' => null];
        }

        try {
            $response = app(WeatherController::class)->show(request(), $icao);
            $payload = $response->getData(true);
        } catch (Throwable) {
            return ['state' => 'error', 'metar' => null, 'observedAt' => null];
        }

        if ($response->getStatusCode() !== 200 || !is_array($payload)) {
            return ['state' => 'error', 'metar' => null, 'observedAt' => null];
        }

        $metar = $payload['metar'] ?? null;

        if (!is_string($metar) || $metar === '') {
            return ['state' => 'missing', 'metar' => null, 'observedAt' => null];
        }

        return [
            'state'      => ($payload['isStale'] ?? false) ? 'stale' : 'loaded',
            'metar'      => $metar,
            'observedAt' => is_string($payload['observedAt'] ?? null) ? $payload['observedAt'] : null,
        ];
    }
}
