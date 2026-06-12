<?php

declare(strict_types=1);

use App\Http\Resources\FlightResource;
use App\Models\Flight;

test('flight resource serializes dpt_time as legacy Hi string alongside structured columns', function (): void {
    $flight = Flight::factory()->create([
        'departure_time' => '08:30:00',
        'arrival_time'   => '14:15:00',
    ]);

    $resource = new FlightResource($flight)->toArray(request());

    // Legacy `Hi` keys for backward-compat API consumers.
    expect($resource)
        ->toHaveKey('dpt_time', '0830')
        ->toHaveKey('arr_time', '1415');

    // Structured TIME columns surface directly via Eloquent serialization;
    // datetime:H:i:s cast emits `H:i:s` strings.
    expect($resource)
        ->toHaveKey('departure_time', '08:30:00')
        ->toHaveKey('arrival_time', '14:15:00');
});

test('flight resource serializes null time columns as null', function (): void {
    $flight = Flight::factory()->create([
        'departure_time' => null,
        'arrival_time'   => null,
    ]);

    $resource = new FlightResource($flight)->toArray(request());

    expect($resource)
        ->toHaveKey('dpt_time', null)
        ->toHaveKey('arr_time', null)
        ->toHaveKey('departure_time', null)
        ->toHaveKey('arrival_time', null);
});
