<?php

declare(strict_types=1);

use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L10BatchOver100;
use Tests\Support\RouteForgeTestHelpers as RF;

it('fires an error when row count strictly exceeds mesh_max_count', function (): void {
    $rows = array_fill(0, 150, RF::row());

    $issues = (new L10BatchOver100())->check(RF::ctx(rows: $rows));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L10')
        ->and($issues[0]->severity)->toBe(LintSeverity::Error)
        ->and($issues[0]->rowIndex)->toBeNull()
        ->and($issues[0]->details)->toBe(['row_count' => 150, 'cap' => 100]);
});

it('does not fire at or below the hard cap', function (): void {
    $rows = array_fill(0, 100, RF::row());

    expect((new L10BatchOver100())->check(RF::ctx(rows: $rows)))->toBe([]);
});

it('honors a custom mesh_max_count from config', function (): void {
    config(['phpvms.routeforge.mesh_max_count' => 50]);

    $rows = array_fill(0, 75, RF::row());

    $issues = (new L10BatchOver100())->check(RF::ctx(rows: $rows));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->details)->toBe(['row_count' => 75, 'cap' => 50]);
});
