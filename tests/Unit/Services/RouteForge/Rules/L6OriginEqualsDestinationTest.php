<?php

declare(strict_types=1);

use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L6OriginEqualsDestination;
use Tests\Support\RouteForgeTestHelpers as RF;

it('emits an error for any row where origin equals destination', function (): void {
    $issues = new L6OriginEqualsDestination()->check(RF::ctx(rows: [
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX']),
        RF::row(['dpt_airport_id' => 'KJFK', 'arr_airport_id' => 'KJFK']),
        RF::row(['dpt_airport_id' => 'KORD', 'arr_airport_id' => 'KDFW']),
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KSFO']),
    ]));

    expect($issues)->toHaveCount(2)
        ->and($issues[0]->ruleId)->toBe('L6')
        ->and($issues[0]->severity)->toBe(LintSeverity::Error)
        ->and($issues[0]->rowIndex)->toBe(1)
        ->and($issues[0]->details)->toBe(['airport' => 'KJFK'])
        ->and($issues[1]->rowIndex)->toBe(3)
        ->and($issues[1]->details)->toBe(['airport' => 'KSFO']);
});

it('skips rows with null dpt or arr airport id', function (): void {
    $issues = new L6OriginEqualsDestination()->check(RF::ctx(rows: [
        RF::row(['dpt_airport_id' => null, 'arr_airport_id' => null]),
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => null]),
        RF::row(['dpt_airport_id' => null, 'arr_airport_id' => 'KLAX']),
    ]));

    expect($issues)->toBe([]);
});

it('does not fire when origin and destination differ', function (): void {
    $issues = new L6OriginEqualsDestination()->check(RF::ctx(rows: [
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX']),
        RF::row(['dpt_airport_id' => 'KJFK', 'arr_airport_id' => 'KBOS']),
    ]));

    expect($issues)->toBe([]);
});
