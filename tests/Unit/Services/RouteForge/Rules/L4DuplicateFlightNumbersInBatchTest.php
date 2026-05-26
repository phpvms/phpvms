<?php

declare(strict_types=1);

use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L4DuplicateFlightNumbersInBatch;
use Tests\Support\RouteForgeTestHelpers as RF;

it('fires an error for intra-batch dup on the strict 4-tuple key', function (): void {
    $issues = (new L4DuplicateFlightNumbersInBatch())->check(RF::ctx(rows: [
        RF::row(['flight_number' => 100]),
        RF::row(['flight_number' => 101]),
        RF::row(['flight_number' => 102]),
        RF::row(['flight_number' => 100]), // collides with row 0
        RF::row(['flight_number' => 103]),
    ]));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L4')
        ->and($issues[0]->severity)->toBe(LintSeverity::Error)
        ->and($issues[0]->rowIndex)->toBe(3)
        ->and($issues[0]->details)->toMatchArray([
            'flight_number'       => 100,
            'first_row_index'     => 0,
            'duplicate_row_index' => 3,
        ]);
});

it('emits one issue per collision beyond the first for N-way ties', function (): void {
    // Three rows share flight_number 100; rule emits an issue for rows 1 + 2
    // (each marks the collision back to first-seen row 0).
    $issues = (new L4DuplicateFlightNumbersInBatch())->check(RF::ctx(rows: [
        RF::row(['flight_number' => 100]),
        RF::row(['flight_number' => 100]),
        RF::row(['flight_number' => 100]),
    ]));

    expect($issues)->toHaveCount(2)
        ->and(array_map(static fn (LintIssue $i): int => $i->rowIndex, $issues))->toBe([1, 2])
        ->and($issues[0]->details['first_row_index'])->toBe(0)
        ->and($issues[1]->details['first_row_index'])->toBe(0);
});

it('treats null / empty / 0 route_code and route_leg as the same canonical absent value', function (): void {
    // L4 normalization collapses null / "" / 0 / "0" to a single sentinel so
    // the strict key matches FlightService duplicate semantics.
    $issues = (new L4DuplicateFlightNumbersInBatch())->check(RF::ctx(rows: [
        RF::row(['flight_number' => 100, 'route_code' => null, 'route_leg' => null]),
        RF::row(['flight_number' => 100, 'route_code' => '', 'route_leg' => 0]),
    ]));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->rowIndex)->toBe(1);
});

it('does NOT fire when route_code or route_leg distinguishes the rows', function (): void {
    $issues = (new L4DuplicateFlightNumbersInBatch())->check(RF::ctx(rows: [
        RF::row(['flight_number' => 100, 'route_code' => 'A', 'route_leg' => null]),
        RF::row(['flight_number' => 100, 'route_code' => 'B', 'route_leg' => null]),
        RF::row(['flight_number' => 100, 'route_code' => 'A', 'route_leg' => 2]),
    ]));

    expect($issues)->toBe([]);
});

it('does not fire when all flight numbers in the batch are unique', function (): void {
    $issues = (new L4DuplicateFlightNumbersInBatch())->check(RF::ctx(rows: [
        RF::row(['flight_number' => 100]),
        RF::row(['flight_number' => 101]),
        RF::row(['flight_number' => 102]),
    ]));

    expect($issues)->toBe([]);
});
