<?php

namespace Tests;

use App\Models\Airport;
use App\Models\User;

final class AirportTest extends TestCase
{
    public function test_saving_airport_from_api_response(): void
    {
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
        $this->assertEquals($airportResponse['icao'], $airport->icao);
        $this->assertEquals($airportResponse['tz'], $airport->timezone);
    }

    public function test_airport_search(): void
    {
        foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
            Airport::factory()->create(['id' => $a, 'icao' => $a]);
        }

        $user = User::factory()->create();

        $uri = '/api/airports/search?search=icao:e';
        $res = $this->get($uri, [], $user);

        $airports = $res->json('data');
        $this->assertCount(1, $airports);
        $this->assertEquals('EGLL', $airports[0]['icao']);

        $uri = '/api/airports/search?search=KJ';
        $res = $this->get($uri, [], $user);

        $airports = $res->json('data');
        $this->assertCount(1, $airports);
        $this->assertEquals('KJFK', $airports[0]['icao']);
    }

    public function test_airport_search_multi_letter(): void
    {
        foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
            Airport::factory()->create(['id' => $a, 'icao' => $a]);
        }

        $user = User::factory()->create();

        $uri = '/api/airports/search?search=Kj';
        $res = $this->get($uri, [], $user);

        $airports = $res->json('data');
        $this->assertCount(1, $airports);
        $this->assertEquals('KJFK', $airports[0]['icao']);
    }

    public function test_airport_search_missing(): void
    {
        foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
            Airport::factory()->create(['id' => $a, 'icao' => $a]);
        }

        $user = User::factory()->create();

        $uri = '/api/airports/search?search=icao:X';
        $res = $this->get($uri, [], $user);

        $airports = $res->json('data');
        $this->assertCount(0, $airports);
    }
}
