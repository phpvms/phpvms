<?php

declare(strict_types=1);

use App\Models\Flight;

/*
 * Companion to `FlightIcaoMutatorTest`. The route_code / route_leg fields
 * collapse null / '' / '0' / 0 to canonical NULL at write time so the strict
 * duplicate-key namespace (the `_dup_key` generated column + UNIQUE index on
 * the flights table) is deterministic. This was previously handled by
 * application-level lint normalization only; storage could drift between '',
 * '0', and 0 freely.
 */

it('canonicalizes empty-string route_code to NULL on direct assignment', function (): void {
    $flight = new Flight();
    $flight->route_code = '';

    expect($flight->route_code)->toBeNull();
});

it('canonicalizes "0" route_code to NULL on direct assignment', function (): void {
    $flight = new Flight();
    $flight->route_code = '0';

    expect($flight->route_code)->toBeNull();
});

it('canonicalizes int-zero route_code to NULL on direct assignment', function (): void {
    $flight = new Flight();
    $flight->route_code = 0;

    expect($flight->route_code)->toBeNull();
});

it('preserves legitimate route_code values', function (): void {
    $flight = new Flight();
    $flight->route_code = 'AB';

    expect($flight->route_code)->toBe('AB');
});

it('preserves null route_code', function (): void {
    $flight = new Flight();
    $flight->route_code = null;

    expect($flight->route_code)->toBeNull();
});

it('canonicalizes empty-string route_leg to NULL', function (): void {
    $flight = new Flight();
    $flight->route_leg = '';

    expect($flight->route_leg)->toBeNull();
});

it('canonicalizes "0" route_leg to NULL', function (): void {
    $flight = new Flight();
    $flight->route_leg = '0';

    expect($flight->route_leg)->toBeNull();
});

it('canonicalizes int-zero route_leg to NULL', function (): void {
    $flight = new Flight();
    $flight->route_leg = 0;

    expect($flight->route_leg)->toBeNull();
});

it('casts string-numeric route_leg to int on read', function (): void {
    $flight = new Flight();
    $flight->route_leg = '5';

    expect($flight->route_leg)->toBe(5)
        ->and($flight->route_leg)->toBeInt();
});

it('preserves int route_leg', function (): void {
    $flight = new Flight();
    $flight->route_leg = 5;

    expect($flight->route_leg)->toBe(5);
});

it('canonicalizes route fields via Model::fill()', function (): void {
    $flight = new Flight();
    $flight->fill([
        'route_code' => '',
        'route_leg'  => '0',
    ]);

    expect($flight->route_code)->toBeNull()
        ->and($flight->route_leg)->toBeNull();
});

it('preserves a mixed fill where one field has a real value and one is empty', function (): void {
    $flight = new Flight();
    $flight->fill([
        'route_code' => 'AB',
        'route_leg'  => '',
    ]);

    expect($flight->route_code)->toBe('AB')
        ->and($flight->route_leg)->toBeNull();
});
