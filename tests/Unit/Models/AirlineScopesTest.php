<?php

declare(strict_types=1);

use App\Models\Airline;

test('Airline::byIcao matches uppercased ICAO codes', function () {
    Airline::factory()->create(['icao' => 'UALAA', 'iata' => 'UA']);
    Airline::factory()->create(['icao' => 'DALAA', 'iata' => 'DL']);

    $results = Airline::byIcao(' ualaa ')->pluck('icao')->all();

    expect($results)->toBe(['UALAA']);
});

test('Airline::selectList falls back to id ordering for unsupported columns', function () {
    $first = Airline::factory()->create([
        'name' => 'Zulu Air',
        'icao' => 'ZZZAA',
        'iata' => 'ZA',
    ]);
    $second = Airline::factory()->create([
        'name' => 'Alpha Air',
        'icao' => 'AAAAA',
        'iata' => 'AA',
    ]);

    $results = Airline::selectList(orderBy: 'unsupported_column');

    expect($results)->toBe([
        $first->id  => $first->name,
        $second->id => $second->name,
    ]);
});
