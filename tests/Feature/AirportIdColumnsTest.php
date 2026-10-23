<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/**
 * Airports are moving off ICAO codes and onto generated ids, so every column
 * that holds one needs room for a 16-character nanoid. Asserted rather than
 * assumed because the widening is spread across six tables, and because
 * `->change()` rewrites a column from the definition it is given -- leaving
 * `->nullable()` off any of these would quietly make the column NOT NULL.
 */
dataset('airport id columns', [
    'airports.id'            => ['airports', 'id', false],
    'aircraft.airport_id'    => ['aircraft', 'airport_id', true],
    'aircraft.hub_id'        => ['aircraft', 'hub_id', true],
    'flights.dpt_airport_id' => ['flights', 'dpt_airport_id', false],
    'flights.arr_airport_id' => ['flights', 'arr_airport_id', false],
    'flights.alt_airport_id' => ['flights', 'alt_airport_id', true],
    'pireps.dpt_airport_id'  => ['pireps', 'dpt_airport_id', false],
    'pireps.arr_airport_id'  => ['pireps', 'arr_airport_id', false],
    'pireps.alt_airport_id'  => ['pireps', 'alt_airport_id', true],
    'subfleets.hub_id'       => ['subfleets', 'hub_id', true],
    'users.home_airport_id'  => ['users', 'home_airport_id', true],
    'users.curr_airport_id'  => ['users', 'curr_airport_id', true],
]);

it('holds 16 characters and keeps its nullability', function (string $table, string $column, bool $nullable): void {
    $definition = collect(Schema::getColumns($table))
        ->firstWhere('name', $column);

    expect($definition)->not->toBeNull()
        ->and($definition['nullable'])->toBe($nullable);

    // SQLite has no varchar length: `SQLiteGrammar::typeString()` emits a bare
    // `varchar` whatever length the migration asked for, and the engine
    // neither stores nor enforces one. There is no width to read back, so the
    // nullability above is all this driver can tell us. The default phpunit
    // connection is sqlite, so without this the whole dataset fails locally.
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        return;
    }

    // The remaining drivers spell the type differently -- `character
    // varying(16)` on postgres, `varchar(16)` on mysql -- so read the width
    // back out of it.
    preg_match('/\((\d+)\)/', (string) $definition['type'], $matches);

    expect($matches[1] ?? null)->toBe('16');
})->with('airport id columns');
