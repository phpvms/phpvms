<?php

use App\Exceptions\AirportNotFound;
use App\Models\Airport;
use App\Repositories\AirportRepository;
use App\Services\AirportService;

it('can create an airport from api response', function () {
    // This is the response from the API
    $airportResponse = [
        'icao'    => 'KJFK',
        'iata'    => 'JFK',
        'name'    => 'John F Kennedy International Airport',
        'city'    => 'New York',
        'country' => 'United States',
        'tz'      => 'America/New_York',
        'lat'     => 40.63980103,
        'lon'     => -73.77890015,
    ];

    $airport = new Airport($airportResponse);
    expect($airport->icao)->toEqual($airportResponse['icao'])
        ->and($airport->timezone)->toEqual($airportResponse['tz']);
});

it('can search airports', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $uri = '/api/airports/search?search=icao:e';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(1)
        ->and($airports[0]['icao'])->toEqual('EGLL');

    $uri = '/api/airports/search?search=KJ';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(1)
        ->and($airports[0]['icao'])->toEqual('KJFK');
});

it('can search airports with multi letter', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $uri = '/api/airports/search?search=Kj';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(1)
        ->and($airports[0]['icao'])->toEqual('KJFK');
});

test('airport search missing', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $uri = '/api/airports/search?search=icao:X';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(0);
});

it('returns cached data if available', function () {
    Config::set('cache.keys.AIRPORT_VACENTRAL_LOOKUP.key', 'air_');
    Cache::shouldReceive('get')->with('air_KJFK')->andReturn(['name' => 'Kennedy']);

    // Resolve from container instead of 'new'
    $result = app(AirportService::class)->lookupAirport('KJFK');

    expect($result)->toBe(['name' => 'Kennedy']);
});

it('creates a generic airport if auto_lookup is disabled', function () {
    Config::set('general.auto_airport_lookup', false);

    // Partial mock the repository so the service uses our mock instead of the real DB
    $this->mock(AirportRepository::class, function ($mock) {
        $mock->shouldReceive('findWithoutFail')->andReturn(null);
    });

    $result = app(AirportService::class)->lookupAirportIfNotFound('KORD');

    expect($result->icao)->toBe('KORD');
    $this->assertDatabaseHas('airports', ['icao' => 'KORD']);
});

it('calculates distance between two known points', function () {
    $this->mock(AirportRepository::class, function ($mock) {
        $mock->shouldReceive('find')->with('KJFK', ['lat', 'lon'])
            ->andReturn((object) ['lat' => 40.6413, 'lon' => -73.7781]);
        $mock->shouldReceive('find')->with('KLAX', ['lat', 'lon'])
            ->andReturn((object) ['lat' => 33.9416, 'lon' => -118.4085]);
    });

    $distance = app(AirportService::class)->calculateDistance('KJFK', 'KLAX');

    expect($distance->toUnit('mi'))->toBeBetween(2472, 2473);
});

it('throws exception when origin airport is missing', function () {
    $this->mock(AirportRepository::class, function ($mock) {
        $mock->shouldReceive('find')->andReturn(null);
    });

    expect(fn () => app(AirportService::class)->calculateDistance('KJFK', 'KLAX'))
        ->toThrow(AirportNotFound::class);
});
