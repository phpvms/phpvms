<?php

declare(strict_types=1);

use App\Http\Requests\SearchAirportsRequest;
use App\Models\Airport;
use App\Queries\AirportSearchQueryV1;

function airportSearchQueryV1For(array $params): AirportSearchQueryV1
{
    $request = SearchAirportsRequest::create('/api/airports', 'GET', $params);
    $request->setContainer(app())->validateResolved();

    return new AirportSearchQueryV1($request);
}

test('AirportSearchQueryV1 returns a Builder ordered by icao asc by default', function () {
    foreach (['KZZZ', 'KAAA', 'KMMM'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $results = airportSearchQueryV1For([])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA', 'KMMM', 'KZZZ']);
});

test('AirportSearchQueryV1 filters to hubs with ?hub=1', function () {
    Airport::factory()->count(3)->create(['hub' => false]);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => true]);

    $results = airportSearchQueryV1For(['hub' => '1'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KORD']);
});

test('AirportSearchQueryV1 filters to hubs with ?hubs=true', function () {
    Airport::factory()->count(2)->create(['hub' => false]);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => true]);

    $results = airportSearchQueryV1For(['hubs' => 'true'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KORD']);
});

test('AirportSearchQueryV1 free-text search matches across icao/iata/name', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'iata' => 'JFK', 'name' => 'Kennedy']);
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'iata' => 'ORD', 'name' => "O'Hare"]);

    // Match by ICAO substring
    $byIcao = airportSearchQueryV1For(['search' => 'JFK'])->build()->get();
    expect($byIcao->pluck('icao')->all())->toBe(['KJFK']);

    // Match by name substring
    $byName = airportSearchQueryV1For(['search' => 'Kennedy'])->build()->get();
    expect($byName->pluck('icao')->all())->toBe(['KJFK']);

    // Match by IATA substring
    $byIata = airportSearchQueryV1For(['search' => 'ORD'])->build()->get();
    expect($byIata->pluck('icao')->all())->toBe(['KORD']);
});

test('AirportSearchQueryV1 field-specific search uses LIKE for icao:value', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $results = airportSearchQueryV1For(['search' => 'icao:E'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['EGLL']);
});

test('AirportSearchQueryV1 free-text search honors searchFields', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'iata' => 'AAA', 'name' => 'Alpha']);
    Airport::factory()->create(['id' => 'EGAA', 'icao' => 'EGAA', 'iata' => 'JFK', 'name' => 'Bravo']);
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'iata' => 'ORD', 'name' => 'JFK Terminal']);

    $results = airportSearchQueryV1For([
        'search'       => 'JFK',
        'searchFields' => 'icao:like',
    ])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KJFK']);
});

test('AirportSearchQueryV1 field-specific search defaults to OR across multiple fields', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'name' => 'Kennedy International']);
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'name' => 'OHare International']);
    Airport::factory()->create(['id' => 'EGLL', 'icao' => 'EGLL', 'name' => 'Heathrow']);

    $results = airportSearchQueryV1For(['search' => 'icao:K;name:Heathrow'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['EGLL', 'KJFK', 'KORD']);
});

test('AirportSearchQueryV1 field-specific search supports searchJoin=and', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'name' => 'Kennedy International']);
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'name' => 'OHare International']);
    Airport::factory()->create(['id' => 'EGLL', 'icao' => 'EGLL', 'name' => 'Heathrow International']);

    $results = airportSearchQueryV1For([
        'search'     => 'icao:K;name:Kennedy',
        'searchJoin' => 'and',
    ])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KJFK']);
});

test('AirportSearchQueryV1 search is case insensitive', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $results = airportSearchQueryV1For(['search' => 'kj'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KJFK']);
});

test('AirportSearchQueryV1 honors orderBy and sortedBy', function () {
    foreach (['KAAA', 'KZZZ', 'KMMM'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $results = airportSearchQueryV1For(['orderBy' => 'icao', 'sortedBy' => 'desc'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KZZZ', 'KMMM', 'KAAA']);
});

test('AirportSearchQueryV1 honors legacy multi-column orderBy syntax', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'country' => 'US']);
    Airport::factory()->create(['id' => 'EGAA', 'icao' => 'EGAA', 'country' => 'UK']);
    Airport::factory()->create(['id' => 'EGLL', 'icao' => 'EGLL', 'country' => 'UK']);

    $results = airportSearchQueryV1For([
        'orderBy'  => 'country;icao',
        'sortedBy' => 'asc;desc',
    ])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['EGLL', 'EGAA', 'KJFK']);
});

test('AirportSearchQueryV1 filters to non-hubs with ?hub=0', function () {
    Airport::factory()->create(['icao' => 'KJFK', 'hub' => true]);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => false]);
    Airport::factory()->create(['icao' => 'KLAX', 'hub' => false]);

    $results = airportSearchQueryV1For(['hub' => '0'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KLAX', 'KORD']);
});

test('AirportSearchQueryV1 composes hub filter and search', function () {
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'hub' => true]);
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'hub' => false]);
    Airport::factory()->create(['id' => 'KAAA', 'icao' => 'KAAA', 'hub' => true]);

    // Search for "K" + only hubs
    $results = airportSearchQueryV1For(['search' => 'icao:K', 'hub' => '1'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA', 'KORD']);
});

test('AirportSearchQueryV1 does not add empty-value LIKE clauses', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);

    // ?search=icao: — empty value should be silently dropped, NOT translated to LIKE '%%'
    $sql = airportSearchQueryV1For(['search' => 'icao:'])->build()->toRawSql();

    // The bug: SQL contains "icao like '%%'" (matches everything by accident)
    // The fix: SQL contains no LIKE clause at all
    expect($sql)->not->toContain("like '%%'");
});
