<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L5ExistingDuplicate;
use Tests\Support\RouteForgeTestHelpers as RF;

/*
 * L5 hits the DB. Uses RefreshDatabase via tests/Pest.php so each test gets
 * an empty schema. Flights factory provides a default bundle automatically.
 *
 * Post-refactor semantics:
 *   - Severity is ERROR (was WARNING)
 *   - Fires only when batch bundle === existing flight's bundle
 *   - Fires only when existing flight is enabled
 *   - Owner-typed flights still excluded
 *   - When ctx->bundle->id is null (new-bundle path), short-circuits
 */

it('fires as ERROR when submitted row matches a same-bundle enabled non-owner flight', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();
    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
        'owner_type'    => null,
    ]);

    $issues = (new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row([
            'airline_id'    => $airline->id,
            'flight_number' => 100,
            'route_code'    => null,
            'route_leg'     => null,
        ]),
    ], bundle: $bundle, airline: $airline));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L5')
        ->and($issues[0]->severity)->toBe(LintIssue::SEVERITY_ERROR)
        ->and($issues[0]->rowIndex)->toBe(0)
        ->and((string) $issues[0]->details['existing_flight_id'])->toBe((string) $existing->id);
});

it('does NOT fire when the matching existing flight is in a different bundle (L12 territory)', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create();
    $bundleB = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
        'owner_type'    => null,
    ]);

    // Submitted batch targets bundle B; the existing flight is in bundle A.
    // L5 should NOT fire — that's L12's job.
    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row([
            'airline_id'    => $airline->id,
            'flight_number' => 100,
        ]),
    ], bundle: $bundleB, airline: $airline)))->toBe([]);
});

it('does NOT fire when the matching existing flight is disabled', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => false, // disabled — doesn't occupy the namespace
        'owner_type'    => null,
    ]);

    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundle, airline: $airline)))->toBe([]);
});

it('ignores owner-typed flights (charter / personal namespace)', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
        'owner_type'    => User::class,
        'owner_id'      => 1,
    ]);

    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundle, airline: $airline)))->toBe([]);
});

it('short-circuits when the lint context bundle has no id (new-bundle path)', function (): void {
    $airline = Airline::factory()->create();
    $existingBundle = FlightBundle::factory()->create();

    // An existing flight in some bundle. The batch is creating a NEW bundle
    // (unsaved, no id), so by definition no existing row can collide.
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $existingBundle->id,
        'flight_number' => 100,
        'enabled'       => true,
    ]);

    // RF::ctx() default uses an unsaved bundle (id is null).
    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], airline: $airline)))->toBe([]);
});

it('returns no issues for an empty row list', function (): void {
    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [])))->toBe([]);
});

it('returns no issues when no submitted row matches existing flights', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 999,
        'enabled'       => true,
    ]);

    expect((new L5ExistingDuplicate())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
        RF::row(['airline_id' => $airline->id, 'flight_number' => 101]),
    ], bundle: $bundle, airline: $airline)))->toBe([]);
});

it('queries the DB once regardless of batch size', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    $rows = [];
    for ($i = 0; $i < 20; $i++) {
        $rows[] = RF::row(['airline_id' => $airline->id, 'flight_number' => 100 + $i]);
    }

    DB::enableQueryLog();
    (new L5ExistingDuplicate())->check(RF::ctx(rows: $rows, bundle: $bundle, airline: $airline));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Exactly one SELECT against `flights`; no N+1.
    expect(collect($queries)->filter(static fn (array $q): bool => str_contains((string) $q['query'], 'flights')))
        ->toHaveCount(1);
});
