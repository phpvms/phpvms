<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\User;
use App\Services\RouteForge\DuplicateChecker;
use Illuminate\Support\Facades\DB;

/*
 * Verifies the bulk duplicate scan: same strict-key semantics as L5 but
 * exposed as a standalone service for the UI's interactive typeahead.
 */

it('matches submitted rows against existing non-owner flights on the strict 4-tuple', function (): void {
    $airline = Airline::factory()->create();
    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 200,
        'route_code'    => '',
        'route_leg'     => '',
        'owner_type'    => null,
    ]);

    $duplicates = (new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 199],
        ['airline_id' => $airline->id, 'flight_number' => 200], // collision
        ['airline_id' => $airline->id, 'flight_number' => 201],
    ]);

    expect($duplicates)->toHaveKey(1)
        ->and($duplicates[1])->toMatchArray([
            'index'          => 1,
            'conflict_field' => 'flight_number',
        ])
        ->and((string) $duplicates[1]['existing_flight_id'])->toBe((string) $existing->id)
        ->and($duplicates[1]['ident'])->toBe($existing->ident);
});

it('returns an empty array for an empty rows payload', function (): void {
    expect((new DuplicateChecker())->check([]))->toBe([]);
});

it('ignores owner-typed flights', function (): void {
    $airline = Airline::factory()->create();
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 200,
        'route_code'    => '',
        'route_leg'     => '',
        'owner_type'    => User::class,
        'owner_id'      => 1,
    ]);

    expect((new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 200],
    ]))->toBe([]);
});

it('treats null / empty / 0 route_code and route_leg as the same canonical value', function (): void {
    $airline = Airline::factory()->create();
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 200,
        'route_code'    => '',
        'route_leg'     => '',
    ]);

    $duplicates = (new DuplicateChecker())->check([
        // Submitted row uses nulls; existing row uses '' for both.
        ['airline_id' => $airline->id, 'flight_number' => 200, 'route_code' => null, 'route_leg' => null],
        ['airline_id' => $airline->id, 'flight_number' => 200, 'route_code' => 0, 'route_leg' => '0'],
    ]);

    expect($duplicates)->toHaveCount(2)
        ->and($duplicates[0]['index'])->toBe(0)
        ->and($duplicates[1]['index'])->toBe(1);
});

it('queries the flights table exactly once regardless of row count', function (): void {
    $airline = Airline::factory()->create();

    $rows = [];
    for ($i = 0; $i < 20; $i++) {
        $rows[] = ['airline_id' => $airline->id, 'flight_number' => 1000 + $i];
    }

    DB::enableQueryLog();
    (new DuplicateChecker())->check($rows);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $flightsQueries = collect($queries)
        ->filter(static fn (array $q): bool => str_contains((string) $q['query'], '"flights"') || str_contains((string) $q['query'], '`flights`'));

    expect($flightsQueries)->toHaveCount(1);
});

it('eager loads airline relation to keep ident accessor free of N+1', function (): void {
    $airline = Airline::factory()->create();
    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        Flight::factory()->create([
            'airline_id'    => $airline->id,
            'flight_number' => 400 + $i,
            'route_code'    => '',
            'route_leg'     => '',
        ]);
        $rows[] = ['airline_id' => $airline->id, 'flight_number' => 400 + $i];
    }

    DB::enableQueryLog();
    $duplicates = (new DuplicateChecker())->check($rows);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Bounded query count regardless of duplicate hits — proves no N+1
    // on the ident accessor's airline lookup. Two queries total: one
    // against flights, one against airlines via the with('airline:...')
    // eager load.
    $relationQueries = collect($queries)
        ->filter(static fn (array $q): bool => str_contains((string) $q['query'], '"flights"')
            || str_contains((string) $q['query'], '"airlines"'));

    expect($relationQueries)->toHaveCount(2)
        ->and($duplicates)->toHaveCount(5);
});
