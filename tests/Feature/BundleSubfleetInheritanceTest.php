<?php

declare(strict_types=1);

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bundle-level subfleet defaults, inherited by a bundle's flights.
 *
 * Every test here drives the real Flight::accessibleSubfleetsFor() — the rule
 * lives in that query and nowhere else, so nothing may reimplement it locally.
 *
 * The three rungs, in order, decided by configuration BEFORE access filtering:
 *   1. the flight's own live `flight_subfleet` pins
 *   2. else its bundle's live `bundle_subfleet` defaults
 *   3. else the fallback (airline-scoped when `flights.only_company_aircraft`)
 *
 * Note the Flight factory shares one "Default" bundle across a test run, so
 * every test below creates its own bundle to keep inheritance isolated.
 */
beforeEach(function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', false);
});

/**
 * A subfleet with an active aircraft, flyable by the given ranks.
 */
function flyableSubfleet(Airline $airline, string $name, array $ranks): Subfleet
{
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => $name]);
    Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    foreach ($ranks as $rank) {
        $rank->subfleets()->attach($subfleet->id);
    }

    return $subfleet;
}

test('a flight with no pins inherits its bundles subfleets', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = flyableSubfleet($airline, 'Bundled A', [$rank]);
    $alsoBundled = flyableSubfleet($airline, 'Bundled B', [$rank]);

    // Reachable only via the fallback — proves rung 2 won, not rung 3.
    flyableSubfleet($airline, 'Unrelated', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach([$bundled->id, $alsoBundled->id]);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$bundled->id, $alsoBundled->id])->sort()->values()->all());
});

test('a flight with its own pins ignores its bundle entirely', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = flyableSubfleet($airline, 'Pinned', [$rank]);
    $bundled = flyableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);
    $flight->subfleets()->attach($pinned->id);

    // Replace, not union: the bundle's default must not leak in alongside.
    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$pinned->id]);
});

test('a flight whose bundle has nothing falls back to todays behaviour', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    foreach (range(1, 3) as $i) {
        flyableSubfleet($airline, 'Open '.$i, [$rank]);
    }

    $bundle = FlightBundle::factory()->create();

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    expect($flight->accessibleSubfleetsFor($user))->toHaveCount(3);
});

test('bundle inherited subfleets are still access filtered by rank', function (): void {
    $airline = Airline::factory()->create();
    $junior = Rank::factory()->create(['name' => 'Junior']);
    $senior = Rank::factory()->create(['name' => 'Senior']);

    $easy = flyableSubfleet($airline, 'Easy', [$junior, $senior]);
    $hard = flyableSubfleet($airline, 'Hard', [$senior]);

    // Junior-flyable but NOT in the bundle. Without this the assertion cannot
    // tell rung 2 from rung 3 — both would yield [Easy] — and the test passes
    // vacuously.
    $outside = flyableSubfleet($airline, 'Outside', [$junior, $senior]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach([$easy->id, $hard->id]);

    $user = User::factory()->create(['rank_id' => $junior->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // Strict subset of the inherited set — not the whole set, and crucially not
    // the fallback, which would have included $outside.
    $got = $flight->accessibleSubfleetsFor($user)->pluck('id')->all();

    expect($got)->toBe([$easy->id])
        ->and($got)->not->toContain($outside->id);
});

test('a bundle assigned only a soft deleted subfleet falls through to the fallback', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $retired = flyableSubfleet($airline, 'Retired', [$rank]);

    foreach (range(1, 3) as $i) {
        flyableSubfleet($airline, 'Open '.$i, [$rank]);
    }

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($retired->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // Filament's DeleteAction soft deletes; nothing detaches the pivot row, so
    // the bundle's only default becomes dangling. Mirrors the rung-1 bug: the
    // bundle now configures nothing LIVE, so the flight must fall back rather
    // than become unbookable for everyone.
    $retired->delete();

    $got = $flight->accessibleSubfleetsFor($user);

    expect($got)->toHaveCount(3)
        ->and($got->pluck('id'))->not->toContain($retired->id);
});

test('an inherited set that access filters to empty stays empty', function (): void {
    $airline = Airline::factory()->create();
    $junior = Rank::factory()->create(['name' => 'Junior']);
    $senior = Rank::factory()->create(['name' => 'Senior']);

    $restricted = flyableSubfleet($airline, 'Restricted', [$senior]);

    // Aircraft the junior CAN fly, reachable only via the fallback.
    foreach (range(1, 3) as $i) {
        flyableSubfleet($airline, 'Open '.$i, [$junior, $senior]);
    }

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($restricted->id);

    $user = User::factory()->create(['rank_id' => $junior->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // The bundle says: fly the Restricted subfleet. The junior cannot. That is
    // a legitimately empty answer — it must NOT drop to the fallback and offer
    // them the rest of the fleet.
    expect($flight->accessibleSubfleetsFor($user))->toBeEmpty();
});

test('only_company_aircraft does not narrow inherited bundle defaults', function (): void {
    updateSetting('flights.only_company_aircraft', true);

    $airline = Airline::factory()->create();
    $foreign = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $theirs = flyableSubfleet($foreign, 'Theirs', [$rank]);
    flyableSubfleet($airline, 'Mine', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($theirs->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // Company-only narrows the fallback only. An explicit bundle assignment is
    // deliberate configuration, exactly as an explicit flight pin is.
    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$theirs->id]);
});

test('detaching from the bundle removes inheritance', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = flyableSubfleet($airline, 'Bundled', [$rank]);
    flyableSubfleet($airline, 'Other', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$bundled->id]);

    $bundle->subfleets()->detach($bundled->id);

    // Back to the fallback: both subfleets, no residual inheritance.
    expect($flight->fresh()->accessibleSubfleetsFor($user))->toHaveCount(2);
});

test('deleting a bundle cascades its subfleet assignments away', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = flyableSubfleet($airline, 'Bundled', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    expect(DB::table('bundle_subfleet')->where('bundle_id', $bundle->id)->count())->toBe(1);

    // `flights.bundle_id` is restrictOnDelete, so a hard delete is only ever
    // reachable for a bundle with no flights left.
    $bundle->forceDelete();

    expect(DB::table('bundle_subfleet')->where('bundle_id', $bundle->id)->count())->toBe(0)
        ->and(Subfleet::find($bundled->id))->not->toBeNull();
});

/**
 * Decision: a bundle's publication state (`enabled`, `deleted_at`) does not
 * affect subfleet inheritance.
 *
 * Eligibility is configuration; publication is `flights.visible`, computed
 * upstream from the bundle's enabled state. Dropping inheritance for a disabled
 * or deleted bundle would silently WIDEN its flights to the whole fleet — the
 * dangerous direction — and assignments must survive intact for restore.
 */
test('a disabled bundle still confers its subfleet defaults', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = flyableSubfleet($airline, 'Bundled', [$rank]);
    flyableSubfleet($airline, 'Other', [$rank]);

    $bundle = FlightBundle::factory()->create(['enabled' => false]);
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$bundled->id]);
});

test('a soft deleted bundle still confers its subfleet defaults', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = flyableSubfleet($airline, 'Bundled', [$rank]);
    flyableSubfleet($airline, 'Other', [$rank]);

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    $bundle->delete();

    expect($bundle->fresh()->trashed())->toBeTrue()
        ->and(DB::table('bundle_subfleet')->where('bundle_id', $bundle->id)->count())->toBe(1)
        ->and($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$bundled->id]);
});

test('a partially hydrated flight refuses to resolve rather than widening', function (): void {
    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $bundled = flyableSubfleet($airline, 'Bundled', [$rank]);
    foreach (range(1, 3) as $i) {
        flyableSubfleet($airline, 'Outside '.$i, [$rank]);
    }

    $bundle = FlightBundle::factory()->create();
    $bundle->subfleets()->attach($bundled->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $full = Flight::factory()->create(['airline_id' => $airline->id, 'bundle_id' => $bundle->id]);

    // Fully hydrated: inherits exactly the bundle's one subfleet.
    expect($full->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$bundled->id]);

    // Hydrated without bundle_id, rung 2 would be vacuously false and the
    // result would silently widen to all four. Must throw instead.
    $partial = Flight::query()->select('id', 'airline_id')->find($full->id);

    expect(fn () => $partial->accessibleSubfleetsFor($user))->toThrow(LogicException::class);
});
