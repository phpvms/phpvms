<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\L12ExistingDuplicateCrossBundle;
use Tests\Support\RouteForgeTestHelpers as RF;

/*
 * L12 fires WARNING when a submitted batch row shares airline+flight_number
 * with an existing enabled, non-owner flight in a DIFFERENT bundle. L5's
 * domain is same-bundle full-tuple match at ERROR severity.
 *
 * Hits the DB via Eloquent like L5. RefreshDatabase per test.
 */

it('fires WARNING when a row matches airline+flight in another enabled bundle', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create(['name' => 'Summer 2026']);
    $bundleB = FlightBundle::factory()->create();

    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 100,
        'enabled'       => true,
        'owner_type'    => null,
    ]);

    $issues = (new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->ruleId)->toBe('L12')
        ->and($issues[0]->severity)->toBe(LintSeverity::Warning)
        ->and($issues[0]->rowIndex)->toBe(0)
        ->and((string) $issues[0]->details['existing_flight_id'])->toBe((string) $existing->id)
        ->and($issues[0]->details['existing_bundle_id'])->toBe($bundleA->id)
        ->and($issues[0]->details['existing_bundle_name'])->toBe('Summer 2026');
});

it('does NOT fire when the match is in the same bundle (L5 territory)', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 100,
        'enabled'       => true,
    ]);

    expect((new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundle, airline: $airline)))->toBe([]);
});

it('does NOT fire when the cross-bundle match is disabled', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create();
    $bundleB = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 100,
        'enabled'       => false, // disabled — outside the namespace
    ]);

    expect((new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline)))->toBe([]);
});

it('does NOT fire when the cross-bundle match is owner-typed', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create();
    $bundleB = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 100,
        'enabled'       => true,
        'owner_type'    => User::class,
        'owner_id'      => 1,
    ]);

    expect((new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline)))->toBe([]);
});

it('returns no issues when no row matches any existing flight', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create();
    $bundleB = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 999,
        'enabled'       => true,
    ]);

    expect((new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline)))->toBe([]);
});

it('emits one issue per matching bundle when the same airline+flight exists in multiple bundles', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create(['name' => 'Bundle A']);
    $bundleB = FlightBundle::factory()->create(['name' => 'Bundle B']);
    $batchBundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 100,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
    ]);
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleB->id,
        'flight_number' => 100,
        'route_code'    => 'X', // different tuple but same airline+flight
        'route_leg'     => null,
        'enabled'       => true,
    ]);

    $issues = (new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $batchBundle, airline: $airline));

    expect($issues)->toHaveCount(2);

    $bundleNames = array_map(static fn (LintIssue $i): string => $i->details['existing_bundle_name'], $issues);
    expect($bundleNames)->toContain('Bundle A')->toContain('Bundle B');
});

it('queries the DB once regardless of batch size', function (): void {
    $airline = Airline::factory()->create();
    $batchBundle = FlightBundle::factory()->create();

    $rows = [];
    for ($i = 0; $i < 20; $i++) {
        $rows[] = RF::row(['airline_id' => $airline->id, 'flight_number' => 100 + $i]);
    }

    DB::enableQueryLog();
    (new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: $rows, bundle: $batchBundle, airline: $airline));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // One SELECT against flights (the bundle eager-load doesn't run when the
    // initial query returns no rows; if it does, that's a second SELECT for
    // the bundles, still bounded — not N+1).
    expect(collect($queries)->filter(static fn (array $q): bool => str_contains((string) $q['query'], 'flights')))
        ->toHaveCount(1);
});

it('returns no issues for an empty row list', function (): void {
    expect((new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [])))->toBe([]);
});

it('fires against ANY existing match when the batch creates a new bundle (id is null)', function (): void {
    $airline = Airline::factory()->create();
    $existingBundle = FlightBundle::factory()->create(['name' => 'Existing']);

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $existingBundle->id,
        'flight_number' => 100,
        'enabled'       => true,
    ]);

    // RF::ctx() default uses an unsaved bundle (id is null) — new-bundle path.
    $issues = (new L12ExistingDuplicateCrossBundle())->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], airline: $airline));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->details['existing_bundle_name'])->toBe('Existing');
});
