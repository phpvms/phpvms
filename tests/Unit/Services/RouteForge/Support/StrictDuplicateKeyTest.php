<?php

declare(strict_types=1);

use App\Models\Flight;
use App\Services\RouteForge\Support\StrictDuplicateKey;

it('produces identical strings for rows with identical 5-tuple keys', function (): void {
    $a = StrictDuplicateKey::forRow([
        'airline_id'    => 7,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
    ], bundleId: 1);

    $b = StrictDuplicateKey::forRow([
        'airline_id'    => 7,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
    ], bundleId: 1);

    expect((string) $a)->toBe((string) $b)
        ->and((string) $a)->toBe('1|7|100||');
});

it('canonicalizes null / empty / 0 / "0" to null for route_code and route_leg', function (): void {
    $a = StrictDuplicateKey::forRow(['airline_id' => 7, 'flight_number' => 100, 'route_code' => null, 'route_leg' => null], bundleId: 1);
    $b = StrictDuplicateKey::forRow(['airline_id' => 7, 'flight_number' => 100, 'route_code' => '', 'route_leg' => 0], bundleId: 1);
    $c = StrictDuplicateKey::forRow(['airline_id' => 7, 'flight_number' => 100, 'route_code' => '0', 'route_leg' => ''], bundleId: 1);

    expect((string) $a)->toBe('1|7|100||')
        ->and((string) $b)->toBe('1|7|100||')
        ->and((string) $c)->toBe('1|7|100||');

    expect($a->routeCode)->toBeNull()
        ->and($a->routeLeg)->toBeNull()
        ->and($b->routeCode)->toBeNull()
        ->and($b->routeLeg)->toBeNull();
});

it('preserves legitimate non-empty route_code and route_leg values', function (): void {
    $key = StrictDuplicateKey::forRow([
        'airline_id'    => 7,
        'flight_number' => 100,
        'route_code'    => 'AB',
        'route_leg'     => '2',
    ], bundleId: 1);

    expect((string) $key)->toBe('1|7|100|AB|2')
        ->and($key->routeCode)->toBe('AB')
        ->and($key->routeLeg)->toBe('2');
});

it('coalesces null bundleId to 0 for the key segment', function (): void {
    // During commit-of-new-bundle the LintContext bundle is unsaved (id is null).
    // The factory accepts ?int bundleId and uses 0 as a sentinel; L5 short-
    // circuits before constructing keys when bundleId is null. L4 still works
    // because every row in a single batch shares the same bundleId.
    $key = StrictDuplicateKey::forRow([
        'airline_id'    => 7,
        'flight_number' => 100,
    ], bundleId: null);

    expect((string) $key)->toBe('0|7|100||')
        ->and($key->bundleId)->toBe(0);
});

it('produces the same key from forFlight and forRow when fields match', function (): void {
    $flight = new Flight([
        'bundle_id'     => 5,
        'airline_id'    => 9,
        'flight_number' => 200,
        'route_code'    => 'AB',
        'route_leg'     => null,
    ]);

    $rowKey = StrictDuplicateKey::forRow([
        'airline_id'    => 9,
        'flight_number' => 200,
        'route_code'    => 'AB',
        'route_leg'     => null,
    ], bundleId: 5);

    $flightKey = StrictDuplicateKey::forFlight($flight);

    expect((string) $flightKey)->toBe((string) $rowKey)
        ->and((string) $flightKey)->toBe('5|9|200|AB|');
});

it('builds cross-bundle partial keys from airline_id + flight_number only', function (): void {
    expect(StrictDuplicateKey::crossBundleKey(7, 100))->toBe('7|100')
        ->and(StrictDuplicateKey::crossBundleKey(9, 200))->toBe('9|200');

    // Different airlines / flights produce different keys
    expect(StrictDuplicateKey::crossBundleKey(7, 100))
        ->not->toBe(StrictDuplicateKey::crossBundleKey(7, 101));
});

it('indexes iterables by stringified key via index()', function (): void {
    $items = [
        ['bundle_id' => 1, 'airline_id' => 7, 'flight_number' => 100, 'route_code' => null, 'route_leg' => null],
        ['bundle_id' => 1, 'airline_id' => 7, 'flight_number' => 101, 'route_code' => null, 'route_leg' => null],
        ['bundle_id' => 2, 'airline_id' => 9, 'flight_number' => 100, 'route_code' => null, 'route_leg' => null],
    ];

    $indexed = StrictDuplicateKey::index(
        $items,
        static fn (array $row): StrictDuplicateKey => StrictDuplicateKey::forRow($row, $row['bundle_id']),
    );

    expect($indexed)->toHaveKeys(['1|7|100||', '1|7|101||', '2|9|100||'])
        ->and($indexed['1|7|100||']['flight_number'])->toBe(100)
        ->and($indexed['2|9|100||']['airline_id'])->toBe(9);
});

it('coalesces missing airline_id / flight_number to 0 in forRow defensive path', function (): void {
    // Defensive: callers should always pass complete rows, but a malformed POST
    // should not raise a TypeError. Missing keys coalesce to 0.
    $key = StrictDuplicateKey::forRow([], bundleId: 1);

    expect((string) $key)->toBe('1|0|0||')
        ->and($key->airlineId)->toBe(0)
        ->and($key->flightNumber)->toBe(0);
});
