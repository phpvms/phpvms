<?php

declare(strict_types=1);

use App\Models\Airport;
use App\Models\Flight;

/*
 * Replaces the prior `App\Observers\FlightObserver` (deleted in
 * routeforge-commit-bulk-insert): the at-rest ICAO uppercase + trim
 * convention now lives on the Flight model itself as `set:` mutators.
 *
 * Bulk-insert paths (RouteForgeService::commit) normalize explicitly because
 * `Model::insert()` bypasses Eloquent's attribute machinery; these tests
 * cover the single-row Eloquent paths that flow through the mutator.
 */

it('uppercases and trims dpt_airport_id on direct assignment', function (): void {
    $flight = new Flight();
    $flight->dpt_airport_id = ' ksfo ';

    expect($flight->dpt_airport_id)->toBe('KSFO');
});

it('uppercases and trims arr_airport_id on direct assignment', function (): void {
    $flight = new Flight();
    $flight->arr_airport_id = "\tklax\n";

    expect($flight->arr_airport_id)->toBe('KLAX');
});

it('uppercases and trims both ICAO fields via Model::fill()', function (): void {
    $flight = new Flight();
    $flight->fill([
        'dpt_airport_id' => 'kjfk ',
        'arr_airport_id' => ' klax',
    ]);

    expect($flight->dpt_airport_id)->toBe('KJFK')
        ->and($flight->arr_airport_id)->toBe('KLAX');
});

it('preserves null ICAO assignments without coercion', function (): void {
    $flight = new Flight();
    $flight->dpt_airport_id = null;
    $flight->arr_airport_id = null;

    expect($flight->dpt_airport_id)->toBeNull()
        ->and($flight->arr_airport_id)->toBeNull();
});

it('persists ICAO values uppercased through Model::create()', function (): void {
    // Pre-create matching airports so the lowercased input survives the
    // mutator into existing FK targets. (The factory's default
    // dpt_airport_id / arr_airport_id closures auto-create airports with
    // uppercase ICAOs; overriding with lowercase strings means the
    // referenced airport must exist after normalization.)
    Airport::factory()->create(['id' => 'KORD']);
    Airport::factory()->create(['id' => 'KMIA']);

    $flight = Flight::factory()->create([
        'dpt_airport_id' => ' kord ',
        'arr_airport_id' => 'kmia',
    ]);

    $flight->refresh();

    expect($flight->dpt_airport_id)->toBe('KORD')
        ->and($flight->arr_airport_id)->toBe('KMIA');
});
