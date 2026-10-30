<?php

declare(strict_types=1);

namespace App\Features\Shell\Livewire;

use App\Http\Controllers\Frontend\WeatherController;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The dispatch header's METAR for the pilot's station.
 *
 * Loaded lazily, so the page does not wait on the weather provider, and read
 * through the same controller as `GET /api/weather/{icao}`, so both give the
 * same text and the same stale rule. There is no polling: it loads once per
 * render, and Retry loads it again.
 *
 * States: loading, missing-station, loaded, stale, missing, error.
 */
#[Lazy]
class HeaderMetar extends Component
{
    #[Locked]
    public ?string $icao = null;

    public function mount(?string $icao = null): void
    {
        $this->icao = filled($icao) ? $icao : null;
    }

    /**
     * Load the METAR again. Re-rendering is the retry.
     */
    public function retry(): void {}

    public function placeholder(): View
    {
        return view('livewire.shell.header-metar', ['state' => 'loading', 'metar' => null, 'observedAt' => null]);
    }

    public function render(): View
    {
        return view('livewire.shell.header-metar', $this->weather());
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
