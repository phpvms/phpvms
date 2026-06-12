<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\RouteForge\Enums\LintSeverity;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\Rules\ExistingDuplicates;
use Tests\Support\RouteForgeTestHelpers as RF;

/*
 * `ExistingDuplicates` is the merged rule that replaces the old
 * `L5ExistingDuplicate` + `L12ExistingDuplicateCrossBundle` pair. It runs ONE
 * bulk query against the airline × flight_number candidate space and emits:
 *
 *   - L5 ERROR  — same-bundle full 5-tuple collision
 *   - L12 WARN  — cross-bundle airline+flight collision
 *
 * Wire shape stays unchanged: emitted issues carry `ruleId='L5'` or
 * `ruleId='L12'` exactly as the old split rules did.
 *
 * All tests use RefreshDatabase via tests/Pest.php.
 */

// ─── L5 (same-bundle, ERROR) ──────────────────────────────────────────────

it('fires L5 ERROR when a row matches a same-bundle enabled non-owner flight', function (): void {
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

    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row([
            'airline_id'    => $airline->id,
            'flight_number' => 100,
            'route_code'    => null,
            'route_leg'     => null,
        ]),
    ], bundle: $bundle, airline: $airline));

    $l5 = array_values(array_filter($issues, static fn (LintIssue $i): bool => $i->ruleId === 'L5'));

    expect($l5)->toHaveCount(1)
        ->and($l5[0]->severity)->toBe(LintSeverity::Error)
        ->and($l5[0]->rowIndex)->toBe(0)
        ->and((string) $l5[0]->details['existing_flight_id'])->toBe((string) $existing->id);
});

it('does NOT fire L5 when the matching existing flight is disabled', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 100,
        'enabled'       => false,
        'owner_type'    => null,
    ]);

    expect(new ExistingDuplicates()->check(RF::ctx(rows: [
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
        'enabled'       => true,
        'owner_type'    => User::class,
        'owner_id'      => 1,
    ]);

    expect(new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundle, airline: $airline)))->toBe([]);
});

it('short-circuits L5 when the lint context bundle has no id (new-bundle path)', function (): void {
    $airline = Airline::factory()->create();
    $existingBundle = FlightBundle::factory()->create();

    // Existing flight in some persisted bundle. The batch creates a NEW
    // bundle (unsaved, id null) so no L5 match is possible. The same match
    // surfaces as L12 instead — asserted in the L12 section below.
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $existingBundle->id,
        'flight_number' => 100,
        'enabled'       => true,
    ]);

    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], airline: $airline));

    $l5 = array_filter($issues, static fn (LintIssue $i): bool => $i->ruleId === 'L5');
    expect($l5)->toBe([]);
});

// ─── L12 (cross-bundle, WARNING) ──────────────────────────────────────────

it('fires L12 WARNING when a row matches airline+flight in another bundle', function (): void {
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

    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline));

    $l12 = array_values(array_filter($issues, static fn (LintIssue $i): bool => $i->ruleId === 'L12'));

    expect($l12)->toHaveCount(1)
        ->and($l12[0]->severity)->toBe(LintSeverity::Warning)
        ->and($l12[0]->rowIndex)->toBe(0)
        ->and((string) $l12[0]->details['existing_flight_id'])->toBe((string) $existing->id)
        ->and($l12[0]->details['existing_bundle_id'])->toBe($bundleA->id)
        ->and($l12[0]->details['existing_bundle_name'])->toBe('Summer 2026');
});

it('does NOT fire L12 when the only match is in the same bundle (L5 territory)', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 100,
        'enabled'       => true,
    ]);

    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundle, airline: $airline));

    $l12 = array_filter($issues, static fn (LintIssue $i): bool => $i->ruleId === 'L12');
    expect($l12)->toBe([]);
});

it('does NOT fire L12 when the cross-bundle match is disabled', function (): void {
    $airline = Airline::factory()->create();
    $bundleA = FlightBundle::factory()->create();
    $bundleB = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundleA->id,
        'flight_number' => 100,
        'enabled'       => false,
    ]);

    expect(new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline)))->toBe([]);
});

it('does NOT fire L12 when the cross-bundle match is owner-typed', function (): void {
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

    expect(new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $bundleB, airline: $airline)))->toBe([]);
});

it('emits one L12 issue per matching bundle when airline+flight exists in multiple bundles', function (): void {
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
        'route_code'    => 'X',
        'route_leg'     => null,
        'enabled'       => true,
    ]);

    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], bundle: $batchBundle, airline: $airline));

    $l12 = array_values(array_filter($issues, static fn (LintIssue $i): bool => $i->ruleId === 'L12'));
    expect($l12)->toHaveCount(2);

    $bundleNames = array_map(static fn (LintIssue $i): string => $i->details['existing_bundle_name'], $l12);
    expect($bundleNames)->toContain('Bundle A')->toContain('Bundle B');
});

it('fires L12 against every existing match when the batch creates a new bundle', function (): void {
    $airline = Airline::factory()->create();
    $existingBundle = FlightBundle::factory()->create(['name' => 'Existing']);

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $existingBundle->id,
        'flight_number' => 100,
        'enabled'       => true,
    ]);

    // RF::ctx() default uses an unsaved bundle (id null) — new-bundle path.
    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
    ], airline: $airline));

    $l12 = array_values(array_filter($issues, static fn (LintIssue $i): bool => $i->ruleId === 'L12'));
    expect($l12)->toHaveCount(1)
        ->and($l12[0]->details['existing_bundle_name'])->toBe('Existing');
});

// ─── Cross-cutting ────────────────────────────────────────────────────────

it('returns no issues for an empty row list', function (): void {
    expect(new ExistingDuplicates()->check(RF::ctx(rows: [])))->toBe([]);
});

it('returns no issues when no submitted row matches any existing flight', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $bundle->id,
        'flight_number' => 999,
        'enabled'       => true,
    ]);

    expect(new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 100]),
        RF::row(['airline_id' => $airline->id, 'flight_number' => 101]),
    ], bundle: $bundle, airline: $airline)))->toBe([]);
});

it('queries the flights table exactly once (one bulk query covers L5 + L12)', function (): void {
    $airline = Airline::factory()->create();
    $bundle = FlightBundle::factory()->create();

    $rows = [];
    for ($i = 0; $i < 20; $i++) {
        $rows[] = RF::row(['airline_id' => $airline->id, 'flight_number' => 100 + $i]);
    }

    DB::enableQueryLog();
    new ExistingDuplicates()->check(RF::ctx(rows: $rows, bundle: $bundle, airline: $airline));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Exactly one SELECT against `flights`. The pre-merge split ran two.
    // The bundle eager-load only fires if the initial query returned rows;
    // here the rows don't match anything so the eager-load skips entirely.
    expect(collect($queries)->filter(static fn (array $q): bool => str_contains((string) $q['query'], 'flights')))
        ->toHaveCount(1);
});

it('emits BOTH L5 and L12 in one pass when a row hits same-bundle AND another row hits cross-bundle', function (): void {
    $airline = Airline::factory()->create();
    $batchBundle = FlightBundle::factory()->create();
    $otherBundle = FlightBundle::factory()->create(['name' => 'Other Bundle']);

    // Same-bundle full-tuple match → L5
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $batchBundle->id,
        'flight_number' => 200,
        'route_code'    => null,
        'route_leg'     => null,
        'enabled'       => true,
    ]);
    // Cross-bundle airline+flight match → L12
    Flight::factory()->create([
        'airline_id'    => $airline->id,
        'bundle_id'     => $otherBundle->id,
        'flight_number' => 300,
        'enabled'       => true,
    ]);

    $issues = new ExistingDuplicates()->check(RF::ctx(rows: [
        RF::row(['airline_id' => $airline->id, 'flight_number' => 200, 'route_code' => null, 'route_leg' => null]),
        RF::row(['airline_id' => $airline->id, 'flight_number' => 300]),
    ], bundle: $batchBundle, airline: $airline));

    $byRule = [];
    foreach ($issues as $issue) {
        $byRule[$issue->ruleId][] = $issue;
    }

    expect($byRule['L5'] ?? [])->toHaveCount(1)
        ->and($byRule['L12'] ?? [])->toHaveCount(1);
});
