<?php

declare(strict_types=1);

use App\Http\Resources\FlightResource;
use App\Models\Flight;

test('flight resource serializes dpt_time as legacy Hi string from new column', function (): void {
    $flight = Flight::factory()->create([
        'departure_time' => '08:30:00',
        'arrival_time'   => '14:15:00',
    ]);

    $resource = (new FlightResource($flight))->toArray(request());

    expect($resource)
        ->toHaveKey('dpt_time', '0830')
        ->toHaveKey('arr_time', '1415')
        ->not->toHaveKey('departure_time')
        ->not->toHaveKey('arrival_time');
});

test('flight resource serializes null time columns as null', function (): void {
    $flight = Flight::factory()->create([
        'departure_time' => null,
        'arrival_time'   => null,
    ]);

    $resource = (new FlightResource($flight))->toArray(request());

    expect($resource)
        ->toHaveKey('dpt_time', null)
        ->toHaveKey('arr_time', null)
        ->not->toHaveKey('departure_time')
        ->not->toHaveKey('arrival_time');
});
