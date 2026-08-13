<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Flight;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class FlightDetailData extends Data
{
    public function __construct(
        public FlightListItemData $summary,
        public ?AirportPointData $departure,
        public ?AirportPointData $arrival,
        public ?AirportPointData $alternate,
        public ?WeatherStationData $departureWeather,
        public ?WeatherStationData $arrivalWeather,
        public ?WeatherStationData $alternateWeather,
        public ?string $scheduledDeparture,
        public ?string $scheduledArrival,
        public ?string $route,
        public ?int $cruiseLevel,
        public string $dispatchUrl,
        public string $ofpPlanningUrl,
    ) {}

    /** @param array<string, int> $saved */
    public static function fromModel(
        Flight $flight,
        array $saved,
        ?FlightDispatchPolicyData $policy = null,
        bool $pilotAtLimit = false,
        bool $operationalLocationBlocked = false,
    ): self {
        return new self(
            summary: FlightListItemData::fromModel(
                $flight,
                $saved,
                $policy,
                $pilotAtLimit,
                $operationalLocationBlocked,
            ),
            departure: self::airportPoint($flight->dpt_airport),
            arrival: self::airportPoint($flight->arr_airport),
            alternate: self::airportPoint($flight->alt_airport),
            departureWeather: WeatherStationData::fromModel($flight->dpt_airport),
            arrivalWeather: WeatherStationData::fromModel($flight->arr_airport),
            alternateWeather: WeatherStationData::fromModel($flight->alt_airport),
            scheduledDeparture: $flight->dpt_time,
            scheduledArrival: $flight->arr_time,
            route: filled($flight->route) ? $flight->route : null,
            cruiseLevel: $flight->level ?: null,
            dispatchUrl: route('frontend.flights.dispatch', $flight->id),
            ofpPlanningUrl: route('frontend.ofp.planning').'?flight_id='.$flight->id,
        );
    }

    private static function airportPoint(?object $airport): ?AirportPointData
    {
        if ($airport === null) {
            return null;
        }

        return new AirportPointData(
            id: $airport->id,
            icao: $airport->icao,
            name: $airport->name,
            lat: $airport->lat === null ? null : (float) $airport->lat,
            lon: $airport->lon === null ? null : (float) $airport->lon,
        );
    }
}
