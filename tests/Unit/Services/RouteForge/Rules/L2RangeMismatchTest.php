<?php

declare(strict_types=1);

use App\Models\Subfleet;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L2RangeMismatch;
use Illuminate\Support\Collection;
use Tests\Support\RouteForgeTestHelpers as RF;

function l2Subfleet(?int $maxRangeNm): Subfleet
{
    return Subfleet::factory()->make([
        'id'           => fake()->unique()->numberBetween(1, 99999),
        'max_range_nm' => $maxRangeNm,
    ]);
}

it('fires per row when every selected subfleet falls short of the distance', function (): void {
    $subfleets = new Collection([l2Subfleet(1500), l2Subfleet(2000)]);
    $rows = [
        RF::row(['distance_nm' => 3500]),
        RF::row(['distance_nm' => 1800]),
        RF::row(['distance_nm' => 5000]),
    ];

    $issues = new L2RangeMismatch()->check(RF::ctx(rows: $rows, selectedSubfleets: $subfleets));

    expect($issues)->toHaveCount(2)
        ->and(array_map(static fn (LintIssue $i): int => $i->rowIndex, $issues))->toBe([0, 2])
        ->and($issues[0]->ruleId)->toBe('L2')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->details)->toMatchArray([
            'distance_nm'        => 3500.0,
            'max_subfleet_range' => 2000,
            'incompatible_count' => 2,
        ]);
});

it('short-circuits to zero issues when any selected subfleet is unrestricted (NULL range)', function (): void {
    $subfleets = new Collection([l2Subfleet(1500), l2Subfleet(null)]);
    $rows = [
        RF::row(['distance_nm' => 3500]),
        RF::row(['distance_nm' => 9999]),
    ];

    expect(new L2RangeMismatch()->check(RF::ctx(rows: $rows, selectedSubfleets: $subfleets)))
        ->toBe([]);
});

it('does not fire when distance is within the largest subfleet range', function (): void {
    $subfleets = new Collection([l2Subfleet(2000), l2Subfleet(4000)]);
    $rows = [RF::row(['distance_nm' => 3500])];

    expect(new L2RangeMismatch()->check(RF::ctx(rows: $rows, selectedSubfleets: $subfleets)))
        ->toBe([]);
});

it('returns no issues when no subfleets are selected (L3 covers that case)', function (): void {
    expect(new L2RangeMismatch()->check(RF::ctx(rows: [RF::row()], selectedSubfleets: new Collection())))
        ->toBe([]);
});
