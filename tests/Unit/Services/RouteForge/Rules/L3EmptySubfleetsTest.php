<?php

declare(strict_types=1);

use App\Models\Subfleet;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L3EmptySubfleets;
use Illuminate\Support\Collection;
use Tests\Support\RouteForgeTestHelpers as RF;

it('fires once batch-wide when no subfleets are selected', function (): void {
    $issues = (new L3EmptySubfleets())->check(RF::ctx(
        rows: [RF::row(), RF::row(['flight_number' => 101])],
        selectedSubfleets: new Collection(),
    ));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L3')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->rowIndex)->toBeNull();
});

it('does not fire when at least one subfleet is selected', function (): void {
    $subfleets = new Collection([Subfleet::factory()->make(['id' => 1])]);

    expect((new L3EmptySubfleets())->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: $subfleets,
    )))->toBe([]);
});
