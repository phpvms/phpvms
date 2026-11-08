<?php

declare(strict_types=1);

namespace Modules\PhpvmsWidgets\Livewire;

use App\Models\Airport;
use App\Services\AirportService;
use App\Support\Metar;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\PhpvmsWidgets\Support\WeatherData;
use Modules\PhpvmsWidgets\Support\WeatherSummary;
use Throwable;

/**
 * Weather (METAR) for one station: the ICAO of the pilot's current airport, else home, unless
 * the theme passes `icao`. Lazy, so a slow weather provider never holds up the page.
 */
#[Lazy]
class Weather extends Component
{
    #[Locked]
    public ?string $icao = null;

    public function mount(): void
    {
        if ($this->icao !== null) {
            return;
        }

        // An airport id is seeded from its ICAO but can differ from it, so the
        // ICAO is looked up rather than taken from the id.
        $user = Auth::user();
        $airportId = $user?->curr_airport_id ?? $user?->home_airport_id;
        $this->icao = $airportId === null ? null : Airport::query()->whereKey($airportId)->value('icao');
    }

    public function render(AirportService $airports): View
    {
        return view('phpvmswidgets::livewire.weather', ['icao' => $this->icao, 'weather' => $this->weather($airports)]);
    }

    /**
     * Rendered outside the component, so the station is passed in, not read from `$this`.
     *
     * @param array<string, mixed> $params
     */
    public function placeholder(array $params = []): View
    {
        return view('phpvmswidgets::livewire.weather', ['icao' => $params['icao'] ?? null, 'weather' => null, 'loading' => true]);
    }

    /**
     * The summary, or null when the station is invalid or has no report, or the provider fails.
     */
    private function weather(AirportService $airports): ?WeatherData
    {
        $icao = strtoupper(trim((string) $this->icao));
        if (preg_match('/^[A-Z0-9]{3,4}$/', $icao) !== 1) {
            return null;
        }

        try {
            $metar = $airports->getMetar($icao);

            return $metar instanceof Metar ? WeatherSummary::from($icao, $metar) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
