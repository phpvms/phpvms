<?php

declare(strict_types=1);

namespace App\Features\Flights\Livewire;

use App\Http\Controllers\Frontend\WeatherController;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * One weather card on the flight page: the METAR for a station.
 *
 * Loaded lazily, so the page does not wait on the weather provider, and read
 * through the same controller as `GET /api/weather/{icao}`. There is no
 * polling; Retry loads it again.
 *
 * States: loading, missing-station, loaded, stale, missing, error.
 */
#[Lazy]
class AirportWeather extends Component
{
    #[Locked]
    public string $label = '';

    #[Locked]
    public ?string $icao = null;

    #[Locked]
    public ?string $emptyLabel = null;

    public function mount(string $label = '', ?string $icao = null, ?string $emptyLabel = null): void
    {
        $this->label = $label;
        $this->icao = filled($icao) ? $icao : null;
        $this->emptyLabel = $emptyLabel;
    }

    /**
     * Load the METAR again. Re-rendering is the retry.
     */
    public function retry(): void {}

    /**
     * @param array<string, mixed> $params The mount parameters; the component is not mounted yet
     */
    public function placeholder(array $params = []): View
    {
        return view('livewire.flights.airport-weather', [
            'label'      => (string) ($params['label'] ?? ''),
            'icao'       => filled($params['icao'] ?? null) ? $params['icao'] : null,
            'emptyLabel' => $params['emptyLabel'] ?? null,
            'state'      => 'loading', 'metar' => null, 'observedAt' => null,
        ]);
    }

    public function render(): View
    {
        return view('livewire.flights.airport-weather', $this->weather());
    }

    /**
     * @return array{state: string, metar: ?string, observedAt: ?string}
     */
    private function weather(): array
    {
        if ($this->icao === null) {
            return ['state' => 'missing-station', 'metar' => null, 'observedAt' => null];
        }

        try {
            $response = app(WeatherController::class)->show(request(), $this->icao);
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
