<?php

declare(strict_types=1);

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;

/**
 * Regression: Flight::accessibleSubfleetsFor applies the access filter BEFORE
 * testing whether the flight has pinned subfleets (app/Models/Flight.php:557-565):
 *
 *     $pinned = Subfleet::query()
 *         ->allowedFor($user)                  // <-- access filter first
 *         ->whereHas('flights', ...)
 *         ->get();
 *
 *     if ($pinned->isNotEmpty()) { return $pinned; }
 *     // ...otherwise fall through to every subfleet the user can access
 *
 * So a pilot who is not qualified for a flight's designated aircraft does not
 * get an empty list — they fall through to the unbounded fallback and are
 * offered the whole rest of the fleet, on a flight that was explicitly
 * restricted away from them.
 *
 * Correct behaviour: eligibility is resolved first, access filtering second.
 * A flight with pins the pilot cannot use resolves to nothing.
 */
test('a pilot unqualified for a flights only pinned subfleet is offered nothing', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', false);

    $airline = Airline::factory()->create();

    $juniorRank = Rank::factory()->create(['name' => 'Junior']);
    $seniorRank = Rank::factory()->create(['name' => 'Senior']);

    // The flight's designated aircraft — senior ranks only.
    $restricted = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Restricted']);
    Aircraft::factory()->create(['subfleet_id' => $restricted->id]);
    $seniorRank->subfleets()->attach($restricted->id);

    // Three unrelated subfleets the junior pilot *can* fly, pinned to nothing.
    foreach (range(1, 3) as $i) {
        $open = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Open '.$i]);
        Aircraft::factory()->create(['subfleet_id' => $open->id]);
        $juniorRank->subfleets()->attach($open->id);
        $seniorRank->subfleets()->attach($open->id);
    }

    $junior = User::factory()->create(['rank_id' => $juniorRank->id, 'airline_id' => $airline->id]);

    $flight = Flight::factory()->create(['airline_id' => $airline->id]);
    $flight->subfleets()->attach($restricted->id);

    // The flight says: fly the Restricted subfleet. The junior pilot cannot.
    // Therefore this flight offers them nothing.
    expect($flight->accessibleSubfleetsFor($junior))->toBeEmpty();
});

test('a pilot qualified for a flights pinned subfleet gets exactly that subfleet', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', false);

    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $pinned = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Pinned']);
    Aircraft::factory()->create(['subfleet_id' => $pinned->id]);
    $rank->subfleets()->attach($pinned->id);

    $other = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Other']);
    Aircraft::factory()->create(['subfleet_id' => $other->id]);
    $rank->subfleets()->attach($other->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);

    $flight = Flight::factory()->create(['airline_id' => $airline->id]);
    $flight->subfleets()->attach($pinned->id);

    // Control: the happy path must keep working — pins win, and only the pin.
    $got = $flight->accessibleSubfleetsFor($user);

    expect($got)->toHaveCount(1)
        ->and($got->first()->id)->toBe($pinned->id);
});

test('a flight with no pins still falls back for a qualified pilot', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', false);

    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    foreach (range(1, 3) as $i) {
        $sf = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Open '.$i]);
        Aircraft::factory()->create(['subfleet_id' => $sf->id]);
        $rank->subfleets()->attach($sf->id);
    }

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id]);

    // Control: genuinely unconfigured flights must keep today's fallback.
    expect($flight->accessibleSubfleetsFor($user))->toHaveCount(3);
});

test('a flight pinned only to a soft deleted subfleet falls back rather than resolving to nothing', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', false);

    $airline = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $retired = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Retired']);
    Aircraft::factory()->create(['subfleet_id' => $retired->id]);
    $rank->subfleets()->attach($retired->id);

    foreach (range(1, 3) as $i) {
        $sf = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Open '.$i]);
        Aircraft::factory()->create(['subfleet_id' => $sf->id]);
        $rank->subfleets()->attach($sf->id);
    }

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id]);
    $flight->subfleets()->attach($retired->id);

    // Admin retires the subfleet. Filament's DeleteAction is a soft delete and
    // nothing detaches the pivot row, so the pin becomes dangling.
    $retired->delete();

    // The flight now has no LIVE pins, so it must fall back — not resolve to
    // nothing, which would make it unbookable for everyone.
    $got = $flight->accessibleSubfleetsFor($user);

    expect($got)->toHaveCount(3)
        ->and($got->pluck('id'))->not->toContain($retired->id);
});

test('only_company_aircraft confines the fallback to the flights airline', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', true);

    $airline = Airline::factory()->create();
    $foreign = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $mine = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Mine']);
    $theirs = Subfleet::factory()->create(['airline_id' => $foreign->id, 'name' => 'Theirs']);
    Aircraft::factory()->create(['subfleet_id' => $mine->id]);
    Aircraft::factory()->create(['subfleet_id' => $theirs->id]);
    $rank->subfleets()->attach([$mine->id, $theirs->id]);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id]);

    // Unpinned flight -> fallback, narrowed to the flight's own airline.
    $got = $flight->accessibleSubfleetsFor($user);

    expect($got)->toHaveCount(1)
        ->and($got->first()->id)->toBe($mine->id);
});

test('only_company_aircraft does not narrow explicit pins', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', true);

    $airline = Airline::factory()->create();
    $foreign = Airline::factory()->create();
    $rank = Rank::factory()->create(['name' => 'Line']);

    $theirs = Subfleet::factory()->create(['airline_id' => $foreign->id, 'name' => 'Theirs']);
    Aircraft::factory()->create(['subfleet_id' => $theirs->id]);
    $rank->subfleets()->attach($theirs->id);

    $user = User::factory()->create(['rank_id' => $rank->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id]);
    $flight->subfleets()->attach($theirs->id);

    // An explicit wet-lease style pin outranks the company-only setting.
    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$theirs->id]);
});

test('a multi pin flight returns exactly the subset the pilot qualifies for', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('flights.only_company_aircraft', false);

    $airline = Airline::factory()->create();
    $junior = Rank::factory()->create(['name' => 'Junior']);
    $senior = Rank::factory()->create(['name' => 'Senior']);

    $easy = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Easy']);
    $hard = Subfleet::factory()->create(['airline_id' => $airline->id, 'name' => 'Hard']);
    Aircraft::factory()->create(['subfleet_id' => $easy->id]);
    Aircraft::factory()->create(['subfleet_id' => $hard->id]);

    $junior->subfleets()->attach($easy->id);
    $senior->subfleets()->attach([$easy->id, $hard->id]);

    $user = User::factory()->create(['rank_id' => $junior->id, 'airline_id' => $airline->id]);
    $flight = Flight::factory()->create(['airline_id' => $airline->id]);
    $flight->subfleets()->attach([$easy->id, $hard->id]);

    // Strict subset — not empty, not the fallback.
    expect($flight->accessibleSubfleetsFor($user)->pluck('id')->all())->toBe([$easy->id]);
});
