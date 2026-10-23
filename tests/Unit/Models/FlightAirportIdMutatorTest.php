<?php

declare(strict_types=1);

use App\Models\Airport;
use App\Models\Flight;

/*
 * `dpt_airport_id` and `arr_airport_id` are foreign keys to `airports.id`.
 * They used to be normalized as ICAO codes -- uppercased and trimmed -- on the
 * assumption that an id *is* a code. It is not, and the uppercasing mangled
 * any id that was not already upper-case, so the row stopped matching the
 * airport it names. Only the trim remains, and these cover both halves of
 * that: whitespace still goes, case no longer does.
 *
 * Bulk-insert paths (RouteForgeService::commit) share these mutators because
 * `buildFlightAttrs()` fills each row through the model before handing the
 * attributes to the query builder.
 */

it('trims dpt_airport_id on direct assignment without folding its case', function (): void {
    $flight = new Flight();
    $flight->dpt_airport_id = ' kSfO ';

    expect($flight->dpt_airport_id)->toBe('kSfO');
});

it('trims arr_airport_id on direct assignment without folding its case', function (): void {
    $flight = new Flight();
    $flight->arr_airport_id = "\tkLaX\n";

    expect($flight->arr_airport_id)->toBe('kLaX');
});

it('trims both airport ids via Model::fill() without folding their case', function (): void {
    $flight = new Flight();
    $flight->fill([
        'dpt_airport_id' => 'kJfK ',
        'arr_airport_id' => ' kLaX',
    ]);

    expect($flight->dpt_airport_id)->toBe('kJfK')
        ->and($flight->arr_airport_id)->toBe('kLaX');
});

it('preserves null airport id assignments without coercion', function (): void {
    $flight = new Flight();
    $flight->dpt_airport_id = null;
    $flight->arr_airport_id = null;

    expect($flight->dpt_airport_id)->toBeNull()
        ->and($flight->arr_airport_id)->toBeNull();
});

/**
 * The airports are created with the exact ids the flight references, mixed
 * case and all, because that is the whole point: the id that goes in is the id
 * that comes back out and still resolves to its airport.
 */
it('persists airport ids as given through Model::create()', function (): void {
    Airport::factory()->create(['id' => 'kOrD', 'icao' => 'KORD']);
    Airport::factory()->create(['id' => 'kMiA', 'icao' => 'KMIA']);

    $flight = Flight::factory()->create([
        'dpt_airport_id' => ' kOrD ',
        'arr_airport_id' => 'kMiA',
    ]);

    $flight->refresh();

    expect($flight->dpt_airport_id)->toBe('kOrD')
        ->and($flight->arr_airport_id)->toBe('kMiA')
        ->and($flight->dpt_airport->icao)->toBe('KORD');
});
