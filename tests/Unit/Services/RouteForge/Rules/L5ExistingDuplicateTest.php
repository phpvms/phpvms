<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\User;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L5ExistingDuplicate;
use Tests\Support\RouteForgeTestHelpers as RF;

/*
 * L5 hits the DB. Uses RefreshDatabase via tests/Pest.php so each test gets
 * an empty schema. Flights factory provides a default bundle automatically.
 */

it('fires when submitted row matches a non-owner flight on the strict 4-tuple', function (): void {
    $airline = Airline::factory()->create();
    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 100,
        'route_code'    => '',
        'route_leg'     => '',
        'owner_type'    => null,
    ]);

    $issues = (new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row([
            'airline_id'    => $airline->id,
            'flight_number' => 100,
            'route_code'    => null,
            'route_leg'     => null,
        ]),
    ], airline: $airline));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L5')
        ->and($issues[0]->severity)->toBe(LintIssue::SEVERITY_WARNING)
        ->and($issues[0]->rowIndex)->toBe(0)
        ->and((string) $issues[0]->details['existing_flight_id'])->toBe((string) $existing->id);
});

it('ignores owner-typed flights (charter / personal namespace)', function (): void {
    $airline = Airline::factory()->create();
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 100,
        'route_code'    => '',
        'route_leg'     => '',
        'owner_type'    => User::class,
        'owner_id'      => 1,
    ]);

    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row([
            'airline_id'    => $airline->id,
            'flight_number' => 100,
        ]),
    ], airline: $airline)))->toBe([]);
});

it('returns no issues for an empty row list', function (): void {
    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [])))->toBe([]);
});

it('returns no issues when no submitted row matches existing flights', function (): void {
    $airline = Airline::factory()->create();
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 999,
        'route_code'    => '',
        'route_leg'     => '',
    ]);

    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
        RF::row(['airline_id' => $airline->id, 'flight_number' => 101]),
    ], airline: $airline)))->toBe([]);
});

it('queries the DB once regardless of batch size', function (): void {
    $airline = Airline::factory()->create();
    $rows = [];
    for ($i = 0; $i < 20; $i++) {
        $rows[] = RF::row(['airline_id' => $airline->id, 'flight_number' => 100 + $i]);
    }

    DB::enableQueryLog();
    (new L5ExistingDuplicate())->check(RF::ctx(rows: $rows, airline: $airline));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Exactly one SELECT against `flights`; no N+1.
    expect(collect($queries)->filter(static fn (array $q): bool => str_contains((string) $q['query'], 'flights')))
        ->toHaveCount(1);
});
