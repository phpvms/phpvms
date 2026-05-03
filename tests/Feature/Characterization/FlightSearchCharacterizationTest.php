<?php

declare(strict_types=1);

use App\Http\Requests\SearchFlightsRequest;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\Typerating;
use App\Queries\FlightSearchQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/*
 * Locks in flight-search behavior across the Phase 6 refactor.
 *
 * Same assertions ran against FlightRepository::searchCriteria (legacy)
 * and now against FlightSearchQuery::build (current). Identical results
 * across the swap = behavior preserved.
 */

/**
 * Run a search through FlightSearchQuery and return the full result collection.
 *
 * Uses ->get() rather than ->paginate() for simpler assertions — the
 * underlying query is identical either way.
 */
function flightSearchRun(array $params, bool $only_active = true): Collection
{
    $base = Request::create('/api/flights/search', 'GET', $params);

    $request = SearchFlightsRequest::createFrom($base);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    return (new FlightSearchQuery())->build($request, $only_active)->get();
}

test('filter by airline id only', function () {
    /** @var Airline $target_airline */
    $target_airline = Airline::factory()->create();
    /** @var Airline $noise_airline */
    $noise_airline = Airline::factory()->create();

    /** @var Flight $target */
    $target = Flight::factory()->create(['airline_id' => $target_airline->id]);
    Flight::factory()->create(['airline_id' => $noise_airline->id]);
    Flight::factory()->create(['airline_id' => $noise_airline->id]);

    $results = flightSearchRun(['airline_id' => $target_airline->id]);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($target->id);
});

test('filter by dep icao uppercases and filters', function () {
    // Create the target airport with a known ICAO id; factory normally creates random ICAOs.
    Airport::factory()->create(['id' => 'KLAX']);
    Airport::factory()->create(['id' => 'KJFK']);

    /** @var Flight $target */
    $target = Flight::factory()->create(['dpt_airport_id' => 'KLAX']);
    Flight::factory()->create(['dpt_airport_id' => 'KJFK']);

    // Pass lowercase — repo must strtoupper before matching.
    $results = flightSearchRun(['dep_icao' => 'klax']);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($target->id)
        ->and($first->dpt_airport_id)->toEqual('KLAX');
});

test('filter by dep icao falls back when primary param is blank', function () {
    Airport::factory()->create(['id' => 'KLAX']);
    Airport::factory()->create(['id' => 'KJFK']);

    /** @var Flight $target */
    $target = Flight::factory()->create(['dpt_airport_id' => 'KLAX']);
    Flight::factory()->create(['dpt_airport_id' => 'KJFK']);

    $results = flightSearchRun([
        'dpt_airport_id' => '',
        'dep_icao'       => 'klax',
    ]);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($target->id);
});

test('filter by arr icao falls back when primary param is blank', function () {
    Airport::factory()->create(['id' => 'KLAX']);
    Airport::factory()->create(['id' => 'KJFK']);

    /** @var Flight $target */
    $target = Flight::factory()->create(['arr_airport_id' => 'KJFK']);
    Flight::factory()->create(['arr_airport_id' => 'KLAX']);

    $results = flightSearchRun([
        'arr_airport_id' => '',
        'arr_icao'       => 'kjfk',
    ]);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($target->id);
});

test('filter by distance range dgt and dlt', function () {
    Flight::factory()->create(['distance' => 500]);
    /** @var Flight $middle */
    $middle = Flight::factory()->create(['distance' => 1000]);
    Flight::factory()->create(['distance' => 2000]);

    $results = flightSearchRun(['dgt' => 800, 'dlt' => 1500]);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($middle->id);
});

test('filter by subfleet id via relation', function () {
    /** @var Subfleet $subfleet */
    $subfleet = Subfleet::factory()->create();

    /** @var Flight $attached */
    $attached = Flight::factory()->create();
    $attached->subfleets()->attach($subfleet->id);

    // Noise flight with no subfleet attached.
    Flight::factory()->create();

    $results = flightSearchRun(['subfleet_id' => $subfleet->id]);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($attached->id);
});

test('filter by type rating id joins through subfleets', function () {
    /** @var Subfleet $subfleet */
    $subfleet = Subfleet::factory()->create();

    $typerating = Typerating::create([
        'name' => 'B737 Type Rating',
        'type' => 'B737',
    ]);
    $typerating->subfleets()->attach($subfleet->id);

    /** @var Flight $attached */
    $attached = Flight::factory()->create();
    $attached->subfleets()->attach($subfleet->id);

    // Noise: a flight with a different subfleet, not linked to the type rating.
    /** @var Subfleet $other_subfleet */
    $other_subfleet = Subfleet::factory()->create();
    /** @var Flight $noise */
    $noise = Flight::factory()->create();
    $noise->subfleets()->attach($other_subfleet->id);

    $results = flightSearchRun(['type_rating_id' => $typerating->id]);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($attached->id);
});

test('filter by icao type joins through aircraft', function () {
    /** @var Subfleet $subfleet */
    $subfleet = Subfleet::factory()->create();
    Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'icao'        => 'B738',
    ]);

    /** @var Flight $attached */
    $attached = Flight::factory()->create();
    $attached->subfleets()->attach($subfleet->id);

    // Noise: subfleet with a different aircraft icao.
    /** @var Subfleet $other_subfleet */
    $other_subfleet = Subfleet::factory()->create();
    Aircraft::factory()->create([
        'subfleet_id' => $other_subfleet->id,
        'icao'        => 'A320',
    ]);
    /** @var Flight $noise */
    $noise = Flight::factory()->create();
    $noise->subfleets()->attach($other_subfleet->id);

    $results = flightSearchRun(['icao_type' => 'B738']);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($attached->id);
});

test('filter by icao type normalizes case', function () {
    /** @var Subfleet $subfleet */
    $subfleet = Subfleet::factory()->create();
    Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'icao'        => 'B738',
    ]);

    /** @var Flight $attached */
    $attached = Flight::factory()->create();
    $attached->subfleets()->attach($subfleet->id);

    $results = flightSearchRun(['icao_type' => 'b738']);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($attached->id);
});

test('plain search matches free text columns', function () {
    Airport::factory()->create(['id' => 'KLAX']);
    Airport::factory()->create(['id' => 'KJFK']);

    /** @var Flight $target */
    $target = Flight::factory()->create([
        'dpt_airport_id' => 'KLAX',
        'callsign'       => 'SEARCHME',
    ]);
    Flight::factory()->create([
        'dpt_airport_id' => 'KJFK',
        'callsign'       => 'IGNOREME',
    ]);

    $results = flightSearchRun(['search' => 'LAX']);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($target->id);
});

test('honors legacy multi-column orderBy syntax', function () {
    Flight::factory()->create(['flight_number' => '100', 'route_code' => 'B']);
    Flight::factory()->create(['flight_number' => '100', 'route_code' => 'A']);
    Flight::factory()->create(['flight_number' => '200', 'route_code' => 'A']);

    $results = flightSearchRun([
        'orderBy'  => 'flight_number;route_code',
        'sortedBy' => 'asc;desc',
    ], only_active: false);

    expect($results->pluck('route_code')->all())->toBe(['B', 'A', 'A'])
        ->and($results->pluck('flight_number')->all())->toBe([100, 100, 200]);
});

test('only active true excludes inactive flights', function () {
    /** @var Flight $active_visible */
    $active_visible = Flight::factory()->create([
        'active'  => true,
        'visible' => true,
    ]);
    Flight::factory()->create([
        'active'  => false,
        'visible' => true,
    ]);
    Flight::factory()->create([
        'active'  => true,
        'visible' => false,
    ]);

    $results = flightSearchRun([], only_active: true);

    expect($results)->toHaveCount(1);
    /** @var Flight $first */
    $first = $results->first();
    expect($first->id)->toEqual($active_visible->id);
});

test('only active false includes inactive flights', function () {
    /** @var Flight $active_visible */
    $active_visible = Flight::factory()->create([
        'active'  => true,
        'visible' => true,
    ]);
    /** @var Flight $inactive_visible */
    $inactive_visible = Flight::factory()->create([
        'active'  => false,
        'visible' => true,
    ]);
    /** @var Flight $active_hidden */
    $active_hidden = Flight::factory()->create([
        'active'  => true,
        'visible' => false,
    ]);

    $results = flightSearchRun([], only_active: false);

    expect($results)->toHaveCount(3);

    // Cast to string on both sides: Flight::$keyType === 'string', so
    // hydrated models return ids as strings, while factory-returned models
    // hold them as ints.
    $ids = $results->pluck('id')->map(fn ($id) => (string) $id)->all();
    expect($ids)->toContain((string) $active_visible->id)
        ->and($ids)->toContain((string) $inactive_visible->id)
        ->and($ids)->toContain((string) $active_hidden->id);
});
