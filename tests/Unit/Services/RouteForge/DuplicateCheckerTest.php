<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\RouteForge\DuplicateChecker;
use Illuminate\Support\Facades\DB;

/*
 * Verifies the bulk duplicate scan: same strict-key semantics as L5/L12 but
 * exposed as a standalone service for the UI's interactive typeahead.
 *
 * Post-refinement: response entries carry `severity` and `kind` so the UI
 * can render same-bundle hits as ERROR and cross-bundle hits as WARNING.
 */

it('classifies same-bundle full-key match as same_bundle / error', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create(['name' => 'Default']);
    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 200,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
        'owner_type'    => null,
    ]);

    $duplicates = (new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 199],
        ['airline_id' => $airline->id, 'flight_number' => 200], // collision
        ['airline_id' => $airline->id, 'flight_number' => 201],
    ], $bundle->id);

    expect($duplicates)->toHaveKey(1)
        ->and($duplicates[1])->toMatchArray([
            'index'                => 1,
            'conflict_field'       => 'flight_number',
            'severity'             => 'error',
            'kind'                 => 'same_bundle',
            'existing_bundle_id'   => $bundle->id,
            'existing_bundle_name' => 'Default',
        ])
        ->and((string) $duplicates[1]['existing_flight_id'])->toBe((string) $existing->id)
        ->and($duplicates[1]['ident'])->toBe($existing->ident);
});

it('classifies cross-bundle airline+flight match as cross_bundle / warning', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create(['name' => 'Summer 2026']);
    $bundleB = FlightBundle::factory()->create();

    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 300,
        'enabled'       => true,
        'owner_type'    => null,
    ]);

    $duplicates = (new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 300],
    ], $bundleB->id);

    expect($duplicates)->toHaveKey(0)
        ->and($duplicates[0])->toMatchArray([
            'index'                => 0,
            'severity'             => 'warning',
            'kind'                 => 'cross_bundle',
            'existing_bundle_id'   => $bundleA->id,
            'existing_bundle_name' => 'Summer 2026',
        ])
        ->and((string) $duplicates[0]['existing_flight_id'])->toBe((string) $existing->id);
});

it('treats every existing match as cross_bundle warning when batch bundle is null (new-bundle path)', function (): void {
    $airline = Airline::factory()->create();
    $existingBundle = FlightBundle::factory()->create(['name' => 'Existing']);

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $existingBundle->id,
        'flight_number' => 400,
        'enabled'       => true,
    ]);

    $duplicates = (new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 400],
    ], null);

    expect($duplicates)->toHaveKey(0)
        ->and($duplicates[0]['severity'])->toBe('warning')
        ->and($duplicates[0]['kind'])->toBe('cross_bundle');
});

it('returns an empty array for an empty rows payload', function (): void {
    expect((new DuplicateChecker())->check([], null))->toBe([]);
});

it('ignores owner-typed flights regardless of bundle', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 200,
        'enabled'       => true,
        'owner_type'    => User::class,
        'owner_id'      => 1,
    ]);

    expect((new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 200],
    ], $bundle->id))->toBe([]);
});

it('ignores disabled flights', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 200,
        'enabled'       => false,
        'owner_type'    => null,
    ]);

    expect((new DuplicateChecker())->check([
        ['airline_id' => $airline->id, 'flight_number' => 200],
    ], $bundle->id))->toBe([]);
});

it('canonicalizes null / empty / "0" route fields when matching', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    // Existing row stores canonical NULL (the Flight model mutators take care
    // of this; we explicitly pass null).
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 200,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
    ]);

    $duplicates = (new DuplicateChecker())->check([
        // Submitted rows use mixed null / '' / 0 / '0' for the same canonical key.
        ['airline_id' => $airline->id, 'flight_number' => 200, 'route_code' => null, 'route_leg' => null],
        ['airline_id' => $airline->id, 'flight_number' => 200, 'route_code' => '', 'route_leg' => 0],
        ['airline_id' => $airline->id, 'flight_number' => 200, 'route_code' => '0', 'route_leg' => '0'],
    ], $bundle->id);

    expect($duplicates)->toHaveCount(3)
        ->and($duplicates[0]['kind'])->toBe('same_bundle')
        ->and($duplicates[1]['kind'])->toBe('same_bundle')
        ->and($duplicates[2]['kind'])->toBe('same_bundle');
});

it('queries the flights table exactly once regardless of row count', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    $rows = [];
    for ($i = 0; $i < 20; $i++) {
        $rows[] = ['airline_id' => $airline->id, 'flight_number' => 1000 + $i];
    }

    DB::enableQueryLog();
    (new DuplicateChecker())->check($rows, $bundle->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $flightsQueries = collect($queries)
        ->filter(static fn (array $q): bool => str_contains((string) $q['query'], '"flights"') || str_contains((string) $q['query'], '`flights`'));

    expect($flightsQueries)->toHaveCount(1);
});

it('eager loads airline + bundle relations to keep ident and bundle_name free of N+1', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();
    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        Flight::factory()->create([
            'airline_id'    => $airline->id,
            'bundle_id'     => $bundle->id,
            'flight_number' => 400 + $i,
            'route_code'    => null,
            'route_leg'     => null,
            'enabled'       => true,
        ]);
        $rows[] = ['airline_id' => $airline->id, 'flight_number' => 400 + $i];
    }

    DB::enableQueryLog();
    $duplicates = (new DuplicateChecker())->check($rows, $bundle->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Three SELECTs total: flights, airlines (via with), flight_bundles (via with).
    $relationQueries = collect($queries)
        ->filter(static fn (array $q): bool => str_contains((string) $q['query'], '"flights"')
            || str_contains((string) $q['query'], '"airlines"')
            || str_contains((string) $q['query'], '"flight_bundles"'));

    expect($relationQueries)->toHaveCount(3)
        ->and($duplicates)->toHaveCount(5);
});
