<?php

declare(strict_types=1);

use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\Rules\L11AirportTimezoneMissing;
use Tests\Support\RouteForgeTestHelpers as RF;

it('fires per row when destination timezone is null', function (): void {
    $issues = new L11AirportTimezoneMissing()->check(RF::ctx(rows: [
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX', 'dpt_timezone' => 'America/Los_Angeles', 'arr_timezone' => null]),
        RF::row(['dpt_airport_id' => 'KJFK', 'arr_airport_id' => 'KBOS', 'dpt_timezone' => 'America/New_York', 'arr_timezone' => 'America/New_York']),
    ]));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L11')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->rowIndex)->toBe(0)
        ->and($issues[0]->details)->toBe(['missing_timezone_airports' => ['KLAX']]);
});

it('fires per row when origin timezone is null', function (): void {
    $issues = new L11AirportTimezoneMissing()->check(RF::ctx(rows: [
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX', 'dpt_timezone' => null, 'arr_timezone' => 'America/Los_Angeles']),
    ]));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->details)->toBe(['missing_timezone_airports' => ['KSFO']]);
});

it('lists both airports when both timezones are null', function (): void {
    $issues = new L11AirportTimezoneMissing()->check(RF::ctx(rows: [
        RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX', 'dpt_timezone' => null, 'arr_timezone' => null]),
    ]));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->details)->toBe(['missing_timezone_airports' => ['KSFO', 'KLAX']]);
});

it('does not fire when both timezones are populated', function (): void {
    expect(new L11AirportTimezoneMissing()->check(RF::ctx(rows: [
        RF::row(['dpt_timezone' => 'America/Los_Angeles', 'arr_timezone' => 'America/New_York']),
        RF::row(['dpt_timezone' => 'Europe/London', 'arr_timezone' => 'Asia/Tokyo']),
    ])))->toBe([]);
});
