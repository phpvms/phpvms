<?php

namespace App\Filament\Forms\Components;

use App\Models\Airport;
use App\Services\AirportService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class AirportSelect extends Select
{
    protected const string NO_LOCAL_RESULTS_VALUE = '__no_local_airports__';

    protected const string VACENTRAL_PREFIX = 'vacentral:';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->searchable()
            ->preload()
            ->native(false)
            ->disableOptionWhen(static fn (string $value): bool => $value === self::NO_LOCAL_RESULTS_VALUE)
            ->live()
            ->afterStateUpdated(static function (AirportSelect $component, AirportService $airportService, mixed $state): void {
                if (!is_string($state) || !str_starts_with($state, self::VACENTRAL_PREFIX)) {
                    return;
                }

                $icao = substr($state, strlen(self::VACENTRAL_PREFIX));
                $airport = $airportService->addAirportFromVacentral($icao);

                if (!$airport) {
                    $component->state(null);

                    Notification::make()
                        ->danger()
                        ->title(__('airports.lookup_failed'))
                        ->body(__('airports.no_airport_found', ['icao' => $icao]))
                        ->send();

                    return;
                }

                $component->state($airport->getKey());
            });
    }

    public function airportRelationship(string $name): static
    {
        $this
            ->relationship($name, 'icao')
            ->getOptionLabelFromRecordUsing(self::airportLabel(...))
            ->getSearchResultsUsing(static fn (AirportSelect $component, AirportService $airportService, string $search): array => $component->airportSearchResults($airportService, $search));

        return $this;
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    protected function airportSearchResults(AirportService $airportService, string $search): array
    {
        $localOptions = Airport::query()
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->whereLike('icao', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('iata', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('name', "%{$search}%", caseSensitive: false);
            })
            ->orderBy('icao')
            ->limit($this->getOptionsLimit())
            ->get()
            ->mapWithKeys(fn (Airport $airport): array => [$airport->getKey() => self::airportLabel($airport)])
            ->all();

        $remoteAirports = collect($airportService->searchAirports($search));
        $existingAirportIds = Airport::query()
            ->whereKey($remoteAirports->pluck('icao')->all())
            ->pluck('id')
            ->all();

        $remoteOptions = $remoteAirports
            ->reject(fn (array $airport): bool => in_array($airport['icao'], $existingAirportIds, true))
            ->mapWithKeys(fn (array $airport): array => [
                self::VACENTRAL_PREFIX.$airport['icao'] => $airport['icao'].' - '.$airport['name'],
            ])
            ->all();

        $results = $localOptions;

        if ($localOptions === []) {
            $results[self::NO_LOCAL_RESULTS_VALUE] = __('airports.no_local_airports_found');
        }

        if ($remoteOptions !== []) {
            $results[__('airports.airport_lookup')] = $remoteOptions;
        }

        return $results;
    }

    protected static function airportLabel(Airport $airport): string
    {
        return $airport->icao.' - '.$airport->name;
    }
}
