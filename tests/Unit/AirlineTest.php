<?php

use App\Models\Airline;
use App\Models\Flight;
use App\Models\Journal;
use App\Models\Pirep;
use App\Services\AirlineService;

it('can add an airline', function () {
    $attrs = Airline::factory()->make([
        'iata' => '',
    ])->toArray();

    $airline = app(AirlineService::class)->createAirline($attrs);
    expect($airline)->not->toBeNull();

    // Ensure only a single journal is created
    $journals = Journal::where([
        'morphed_type' => Airline::class,
        'morphed_id'   => $airline->id,
    ])->get();

    expect($journals)->toHaveCount(1);

    // Add another airline, also blank IATA
    $attrs = Airline::factory()->make([
        'iata' => '',
    ])->toArray();
    $airline = app(AirlineService::class)->createAirline($attrs);
    expect($airline)->not->toBeNull();
});

it('cannot delete airline with flight', function () {
    $airline = Airline::factory()->create();
    Flight::factory()->create([
        'airline_id' => $airline->id,
    ]);

    expect(app(AirlineService::class)->canDeleteAirline($airline))->toBeFalse();
});

it('cannot delete airline with pirep', function () {
    $airline = Airline::factory()->create();
    Pirep::factory()->create([
        'airline_id' => $airline->id,
    ]);

    expect(app(AirlineService::class)->canDeleteAirline($airline))->toBeFalse();
});
