<?php

declare(strict_types=1);

use App\Models\Fare;
use App\Models\Subfleet;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L7SubfleetsHaveNoFares;
use Illuminate\Support\Collection;
use Tests\Support\RouteForgeTestHelpers as RF;

function l7Subfleet(bool $withFares): Subfleet
{
    $subfleet = Subfleet::factory()->create();
    if ($withFares) {
        $subfleet->fares()->attach(Fare::factory()->create()->id);
    }

    // Eager-load fares so the rule's $subfleet->fares lookup avoids re-querying.
    return $subfleet->fresh(['fares']);
}

it('fires when every selected subfleet has zero fares', function (): void {
    $subfleets = new Collection([l7Subfleet(false), l7Subfleet(false)]);

    $issues = new L7SubfleetsHaveNoFares()->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: $subfleets,
    ));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L7')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->rowIndex)->toBeNull()
        ->and($issues[0]->details)->toBe(['subfleet_count' => 2]);
});

it('does not fire when at least one selected subfleet has fares', function (): void {
    $subfleets = new Collection([l7Subfleet(false), l7Subfleet(true)]);

    expect(new L7SubfleetsHaveNoFares()->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: $subfleets,
    )))->toBe([]);
});

it('does not fire when no subfleets are selected (L3 covers that case)', function (): void {
    expect(new L7SubfleetsHaveNoFares()->check(RF::ctx(
        rows: [RF::row()],
        selectedSubfleets: new Collection(),
    )))->toBe([]);
});
