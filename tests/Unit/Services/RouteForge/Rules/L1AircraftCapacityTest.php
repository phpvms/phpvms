<?php

declare(strict_types=1);

use App\Models\Subfleet;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L1AircraftCapacity;
use Illuminate\Support\Collection;
use Tests\Support\RouteForgeTestHelpers as RF;

/*
 * L1 fires when selected aircraft < row_count / 2 (integer division).
 * Threshold semantics match the TS Math.floor: 20 rows → threshold 10.
 */

function l1Subfleet(int $aircraftCount): Subfleet
{
    $sf = Subfleet::factory()->make(['id' => fake()->unique()->numberBetween(1, 99999)]);
    $sf->setAttribute('aircraft_count', $aircraftCount);

    return $sf;
}

it('fires when selected aircraft are below half the row count', function (): void {
    $subfleets = new Collection([l1Subfleet(9)]);
    $rows = array_fill(0, 20, RF::row());

    $issues = (new L1AircraftCapacity())->check(RF::ctx(rows: $rows, selectedSubfleets: $subfleets));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L1')
        ->and($issues[0]->severity)->toBe(LintIssue::SEVERITY_WARNING)
        ->and($issues[0]->rowIndex)->toBeNull()
        ->and($issues[0]->details)->toMatchArray([
            'selected_aircraft_count' => 9,
            'row_count'               => 20,
            'threshold'               => 10,
        ]);
});

it('does not fire when selected aircraft meet the half-ratio threshold', function (): void {
    $subfleets = new Collection([l1Subfleet(10)]);
    $rows = array_fill(0, 20, RF::row());

    expect((new L1AircraftCapacity())->check(RF::ctx(rows: $rows, selectedSubfleets: $subfleets)))
        ->toBe([]);
});

it('does not fire when no rows are present', function (): void {
    $subfleets = new Collection([l1Subfleet(0)]);

    expect((new L1AircraftCapacity())->check(RF::ctx(rows: [], selectedSubfleets: $subfleets)))
        ->toBe([]);
});

it('sums aircraft across multiple selected subfleets', function (): void {
    // 3 + 4 = 7 selected vs threshold 5 → meets, no warning.
    $subfleets = new Collection([l1Subfleet(3), l1Subfleet(4)]);
    $rows = array_fill(0, 11, RF::row());

    expect((new L1AircraftCapacity())->check(RF::ctx(rows: $rows, selectedSubfleets: $subfleets)))
        ->toBe([]);
});
