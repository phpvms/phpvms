<?php

declare(strict_types=1);

use App\Http\Requests\SearchFlightsRequest;
use App\Models\Airport;
use App\Models\Flight;
use App\Queries\FlightSearchQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * `dpt_airport_id` and `dep_icao` resolve to the same airport today only
 * because an airport id *is* its ICAO. They are not the same kind of value,
 * and the difference shows up in case handling: a code can be folded to match
 * how codes are stored, an id cannot. Asserted so the id parameter does not
 * quietly acquire code semantics again once ids become generated nanoids.
 */
function flightIdsMatching(array $params): array
{
    return flightSearchFor($params)->pluck('id')->all();
}

/**
 * Whether the fold happened is a property of the query we build, not of the
 * rows that come back: mysql's default `utf8mb4_unicode_ci` matches `kjfk`
 * against a stored `KJFK` on its own, so a row-level assertion would only
 * hold on postgres. The binding is what this code decides, so assert that.
 */
function flightSearchBindings(array $params): array
{
    return flightSearchFor($params)->getBindings();
}

function flightSearchFor(array $params): Builder
{
    return new FlightSearchQuery()
        ->build(SearchFlightsRequest::create('/api/flights/search', 'GET', $params));
}

it('matches an airport id exactly and folds case only for the icao alias', function (): void {
    $departure = Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);
    $flight = Flight::factory()->create(['dpt_airport_id' => $departure->id]);

    expect(flightIdsMatching(['dpt_airport_id' => 'KJFK']))->toBe([$flight->id])
        ->and(flightSearchBindings(['dpt_airport_id' => 'kjfk']))->toContain('kjfk')
        ->and(flightSearchBindings(['dep_icao' => 'kjfk']))->toContain('KJFK')
        ->and(flightIdsMatching(['dep_icao' => 'kjfk']))->toBe([$flight->id])
        ->and(flightIdsMatching(['dep_icao' => 'KJFK']))->toBe([$flight->id]);
});

it('matches an arrival airport id exactly and folds case only for the icao alias', function (): void {
    $arrival = Airport::factory()->create(['id' => 'KLAX', 'icao' => 'KLAX']);
    $flight = Flight::factory()->create(['arr_airport_id' => $arrival->id]);

    expect(flightIdsMatching(['arr_airport_id' => 'KLAX']))->toBe([$flight->id])
        ->and(flightSearchBindings(['arr_airport_id' => 'klax']))->toContain('klax')
        ->and(flightSearchBindings(['arr_icao' => 'klax']))->toContain('KLAX')
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

/**
 * The widened id column is only usable if a filter can carry a full-width id
 * end to end. The airport is created with its own id rather than through the
 * ICAO, which the model allows now that the two are no longer the same value.
 *
 * Mixed case on purpose: a nanoid carries both, and nothing on the write path
 * folds it any more, so the id that goes in is the id a filter has to find.
 */
it('filters on a full-width airport id', function (): void {
    $departure = Airport::factory()->create(['id' => 'V1a2B3c4D5e6F7g8', 'icao' => 'KJFK']);
    $flight = Flight::factory()->create(['dpt_airport_id' => $departure->id]);

    expect(flightIdsMatching(['dpt_airport_id' => 'V1a2B3c4D5e6F7g8']))->toBe([$flight->id]);
});
