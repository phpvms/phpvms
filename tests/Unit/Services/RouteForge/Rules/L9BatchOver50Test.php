<?php

declare(strict_types=1);

use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L9BatchOver50;
use Tests\Support\RouteForgeTestHelpers as RF;

it('fires when row count strictly exceeds mesh_warn_count', function (): void {
    $rows = array_fill(0, 75, RF::row());

    $issues = (new L9BatchOver50())->check(RF::ctx(rows: $rows));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L9')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->rowIndex)->toBeNull()
        ->and($issues[0]->details)->toBe(['row_count' => 75, 'threshold' => 50]);
});

it('does not fire at or below the soft threshold', function (): void {
    $rows = array_fill(0, 50, RF::row());

    expect((new L9BatchOver50())->check(RF::ctx(rows: $rows)))->toBe([]);
});

it('honors a custom mesh_warn_count from config', function (): void {
    config(['phpvms.routeforge.mesh_warn_count' => 20]);

    $rows = array_fill(0, 25, RF::row());

    $issues = (new L9BatchOver50())->check(RF::ctx(rows: $rows));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->details)->toBe(['row_count' => 25, 'threshold' => 20]);
});
