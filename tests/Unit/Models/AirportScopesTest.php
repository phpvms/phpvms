<?php

declare(strict_types=1);

use App\Models\Airport;

test('Airport::byHub returns only hubs', function () {
    Airport::factory()->count(3)->create(['hub' => false]);
    Airport::factory()->create(['hub' => true, 'icao' => 'KORD']);

    $results = Airport::byHub()->get();

    expect($results->pluck('icao')->all())->toBe(['KORD']);
});

test('Airport::orderByIcao returns rows in ascending icao order', function () {
    foreach (['KZZZ', 'KAAA', 'KMMM'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $results = Airport::orderByIcao()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA', 'KMMM', 'KZZZ']);
});

test('Airport::active excludes soft-deleted airports', function () {
    Airport::factory()->create(['icao' => 'KAAA']);
    $trashed = Airport::factory()->create(['icao' => 'KBBB']);
    $trashed->delete();

    $results = Airport::active()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA']);
});

test('Airport scopes compose with each other', function () {
    Airport::factory()->create(['icao' => 'KZZZ', 'hub' => true]);
    Airport::factory()->create(['icao' => 'KAAA', 'hub' => true]);
    Airport::factory()->create(['icao' => 'KMMM', 'hub' => false]);

    $results = Airport::byHub()->orderByIcao()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA', 'KZZZ']);
});

test('Airport::resolveRouteBinding upcases the incoming ICAO', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);

    $resolved = (new Airport())->resolveRouteBinding('kjfk');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe('KJFK');
});

test('Airport::resolveRouteBinding returns null for unknown ICAO', function () {
    $resolved = (new Airport())->resolveRouteBinding('XXXX');

    expect($resolved)->toBeNull();
});
