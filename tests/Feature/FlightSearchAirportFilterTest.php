<?php

declare(strict_types=1);

use App\Http\Requests\SearchFlightsRequest;
use App\Models\Airport;
use App\Models\Flight;
use App\Queries\FlightSearchQuery;

/**
 * `dpt_airport_id` and `dep_icao` resolve to the same airport today only
 * because an airport id *is* its ICAO. They are not the same kind of value,
 * and the difference shows up in case handling: a code can be folded to match
 * how codes are stored, an id cannot. Asserted so the id parameter does not
 * quietly acquire code semantics again once ids become generated nanoids.
 */
function flightIdsMatching(array $params): array
{
    return new FlightSearchQuery()
        ->build(SearchFlightsRequest::create('/api/flights/search', 'GET', $params))
        ->pluck('id')
        ->all();
}

it('matches an airport id exactly and folds case only for the icao alias', function (): void {
    $departure = Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);
    $flight = Flight::factory()->create(['dpt_airport_id' => $departure->id]);

    expect(flightIdsMatching(['dpt_airport_id' => 'KJFK']))->toBe([$flight->id])
        ->and(flightIdsMatching(['dpt_airport_id' => 'kjfk']))->toBe([])
        ->and(flightIdsMatching(['dep_icao' => 'kjfk']))->toBe([$flight->id])
        ->and(flightIdsMatching(['dep_icao' => 'KJFK']))->toBe([$flight->id]);
});

it('matches an arrival airport id exactly and folds case only for the icao alias', function (): void {
    $arrival = Airport::factory()->create(['id' => 'KLAX', 'icao' => 'KLAX']);
    $flight = Flight::factory()->create(['arr_airport_id' => $arrival->id]);

    expect(flightIdsMatching(['arr_airport_id' => 'KLAX']))->toBe([$flight->id])
        ->and(flightIdsMatching(['arr_airport_id' => 'klax']))->toBe([])
        ->and(flightIdsMatching(['arr_icao' => 'klax']))->toBe([$flight->id]);
});

/**
 * An id that is not all letters is not exotic -- the airport factory draws
 * from A-Z0-9, and a nanoid will carry digits as well -- so an id filter has
 * to survive one.
 */
it('filters on an airport id that carries digits', function (): void {
    $departure = Airport::factory()->create(['id' => 'K6L3', 'icao' => 'K6L3']);
    $flight = Flight::factory()->create(['dpt_airport_id' => $departure->id]);

    expect(flightIdsMatching(['dpt_airport_id' => 'K6L3']))->toBe([$flight->id]);
});
