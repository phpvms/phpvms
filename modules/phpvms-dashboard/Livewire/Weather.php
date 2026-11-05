<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Livewire;

use App\Services\AirportService;
use App\Support\Metar;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\PhpvmsDashboard\Http\Data\WeatherData;
use Modules\PhpvmsDashboard\Support\WeatherSummary;
use Throwable;

/**
 * Weather (METAR) for one station: the pilot's current airport, else home, unless
 * the theme passes `icao`. Lazy, so a slow weather provider never holds up the page.
 */
#[Lazy]
class Weather extends Component
{
    #[Locked]
    public ?string $icao = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->icao ??= $user?->curr_airport_id ?? $user?->home_airport_id;
    }

    public function render(AirportService $airports): View
    {
        return view('phpvmsdashboard::livewire.weather', ['icao' => $this->icao, 'weather' => $this->weather($airports)]);
    }

    /**
     * Rendered outside the component, so the station is passed in, not read from `$this`.
     *
     * @param array<string, mixed> $params
     */
    public function placeholder(array $params = []): View
    {
        return view('phpvmsdashboard::livewire.weather', ['icao' => $params['icao'] ?? null, 'weather' => null, 'loading' => true]);
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
