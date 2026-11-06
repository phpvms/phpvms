<?php

declare(strict_types=1);

namespace App\Features\Flights\Livewire;

use App\Features\Flights\Actions\DescribeMetar;
use App\Features\Flights\Actions\LoadStationWeather;
use App\Http\Data\MetarDescriptionData;
use App\Models\Airport;
use App\Support\Metar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

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
            'label'       => (string) ($params['label'] ?? ''),
            'icao'        => filled($params['icao'] ?? null) ? $params['icao'] : null,
            'emptyLabel'  => $params['emptyLabel'] ?? null,
            'state'       => 'loading', 'metar' => null, 'observedAt' => null,
            'description' => null, 'observedClock' => null,
        ]);
    }

    public function render(): View
    {
        return view('livewire.flights.airport-weather', $this->weather());
    }

    /**
     * The loaded weather, plus the plain-language description of the METAR and
     * its observation time as a UTC clock ("Today 23:35Z").
     *
     * @return array{state: string, metar: ?string, observedAt: ?string, description: ?MetarDescriptionData, observedClock: ?string}
     */
    private function weather(): array
    {
        $weather = app(LoadStationWeather::class)->handle($this->icao);

        $description = null;
        if ($weather['metar'] !== null) {
            $airport = Airport::query()->withTrashed()->where('icao', $this->icao)->first(['id', 'lat', 'lon']);
            $description = app(DescribeMetar::class)->handle(
                new Metar($weather['metar']),
                $airport?->lat === null ? null : (float) $airport->lat,
                $airport?->lon === null ? null : (float) $airport->lon,
            );
        }

        return $weather + [
            'description'   => $description,
            'observedClock' => $this->observedClock($weather['observedAt']),
        ];
    }

    private function observedClock(?string $observedAt): ?string
    {
        $timestamp = $observedAt === null ? false : strtotime($observedAt);
        if ($timestamp === false) {
            return null;
        }

        $time = CarbonImmutable::createFromTimestampUTC($timestamp);

        return ($time->isSameDay(CarbonImmutable::now('UTC')) ? 'Today' : $time->format('j M')).' '.$time->format('H:i').'Z';
    }
}
