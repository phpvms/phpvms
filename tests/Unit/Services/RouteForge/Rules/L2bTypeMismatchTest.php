<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Models\Subfleet;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L2bTypeMismatch;
use Illuminate\Support\Collection;
use Tests\Support\RouteForgeTestHelpers as RF;

/**
 * @param array<FlightType>|null $routeTypes NULL = unrestricted
 */
function l2bSubfleet(?array $routeTypes): Subfleet
{
    return Subfleet::factory()->make([
        'id'          => fake()->unique()->numberBetween(1, 99999),
        'route_types' => $routeTypes,
    ]);
}

it('fires per row when every selected subfleet restricts to other flight types', function (): void {
    $subfleets = new Collection([
        l2bSubfleet([FlightType::SCHED_PAX, FlightType::SCHED_CARGO]),
        l2bSubfleet([FlightType::SCHED_PAX]),
    ]);

    $issues = new L2bTypeMismatch()->check(RF::ctx(
        rows: [RF::row(), RF::row(['flight_number' => 101]), RF::row(['flight_number' => 102])],
        selectedSubfleets: $subfleets,
        flightType: FlightType::CHARTER_CARGO_MAIL,
    ));

    expect($issues)->toHaveCount(3)
        ->and($issues[0]->ruleId)->toBe('L2b')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and(array_map(static fn (LintIssue $i): int => $i->rowIndex, $issues))->toBe([0, 1, 2])
        ->and($issues[0]->details)->toBe(['flight_type' => FlightType::CHARTER_CARGO_MAIL->value]);
});

it('does not fire when at least one selected subfleet has NULL route_types (unrestricted)', function (): void {
    $subfleets = new Collection([
        l2bSubfleet([FlightType::SCHED_PAX]),
        l2bSubfleet(null), // unrestricted
    ]);

    expect(new L2bTypeMismatch()->check(RF::ctx(
        rows: [RF::row(), RF::row(['flight_number' => 101])],
        selectedSubfleets: $subfleets,
        flightType: FlightType::CHARTER_CARGO_MAIL,
    )))->toBe([]);
});

it('does not fire when the batch flight_type is contained in any selected subfleet', function (): void {
    $subfleets = new Collection([
        l2bSubfleet([FlightType::SCHED_PAX, FlightType::CHARTER_CARGO_MAIL]),
    ]);

    expect(new L2bTypeMismatch()->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: $subfleets,
        flightType: FlightType::CHARTER_CARGO_MAIL,
    )))->toBe([]);
});

it('does not fire when no flight_type is set', function (): void {
    $subfleets = new Collection([l2bSubfleet([FlightType::SCHED_PAX])]);

    expect(new L2bTypeMismatch()->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: $subfleets,
        flightType: null,
    )))->toBe([]);
});

it('does not fire when no subfleets are selected (L3 covers that case)', function (): void {
    expect(new L2bTypeMismatch()->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: new Collection(),
        flightType: FlightType::CHARTER_CARGO_MAIL,
    )))->toBe([]);
});
