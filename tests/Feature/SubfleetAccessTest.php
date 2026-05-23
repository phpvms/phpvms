<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\Typerating;
use App\Models\User;

/**
 * Build a deterministic fixture covering the rank/type-rating/airport matrix.
 *
 * Topology:
 *   - rank R: attached to subfleets A, B
 *   - typerating T1: attached to subfleets B, C
 *   - typerating T2: attached to subfleet D
 *   - user holds T1 only
 *   - subfleet A has one aircraft at KJFK
 *   - subfleet B has one aircraft at KLAX
 *   - subfleet C has one aircraft at KJFK
 *   - subfleet D has one aircraft at KJFK
 *   - flight F departs KJFK with no pinned subfleets
 */
function seedAccessFixture(): array
{
    Airport::factory()->create(['id' => 'KJFK']);
    Airport::factory()->create(['id' => 'KLAX']);

    $sfA = Subfleet::factory()->create(['name' => 'A']);
    $sfB = Subfleet::factory()->create(['name' => 'B']);
    $sfC = Subfleet::factory()->create(['name' => 'C']);
    $sfD = Subfleet::factory()->create(['name' => 'D']);

    $aircraftA = $sfA->aircraft()->create([
        'name' => 'AC-A', 'subfleet_id' => $sfA->id, 'airport_id' => 'KJFK',
    ]);
    $aircraftB = $sfB->aircraft()->create([
        'name' => 'AC-B', 'subfleet_id' => $sfB->id, 'airport_id' => 'KLAX',
    ]);
    $aircraftC = $sfC->aircraft()->create([
        'name' => 'AC-C', 'subfleet_id' => $sfC->id, 'airport_id' => 'KJFK',
    ]);
    $aircraftD = $sfD->aircraft()->create([
        'name' => 'AC-D', 'subfleet_id' => $sfD->id, 'airport_id' => 'KJFK',
    ]);

    $rank = Rank::factory()->create();
    $rank->subfleets()->attach([$sfA->id, $sfB->id]);

    $t1 = Typerating::create(['name' => 'T1', 'type' => 'T1', 'active' => 1]);
    $t2 = Typerating::create(['name' => 'T2', 'type' => 'T2', 'active' => 1]);
    $t1->subfleets()->attach([$sfB->id, $sfC->id]);
    $t2->subfleets()->attach([$sfD->id]);

    $user = User::factory()->create(['rank_id' => $rank->id]);
    $t1->users()->attach($user->id);

    $airline = Airline::factory()->create();
    $flight = Flight::factory()->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => 'KJFK',
    ]);

    return ['user' => $user, 'flight' => $flight, 'sfA' => $sfA, 'sfB' => $sfB, 'sfC' => $sfC, 'sfD' => $sfD, 'aircraftA' => $aircraftA, 'aircraftB' => $aircraftB, 'aircraftC' => $aircraftC, 'aircraftD' => $aircraftD];
}

dataset('access matrix',
    // [rank_restrict, type_restrict, expected_subfleet_names]
    // Topology:
    //   - rank attaches A, B
    //   - user's typerating attaches B, C
    //   - intersection: B
    //   - union (neither restricted): A, B, C, D
    fn (): array => [
        'no restrictions'                 => [false, false, ['A', 'B', 'C', 'D']],
        'rank only'                       => [true, false, ['A', 'B']],
        'typerating only'                 => [false, true, ['B', 'C']],
        'rank AND typerating (intersect)' => [true, true, ['B']],
    ]);

it('returns the correct allowed subfleets per setting combination', function (
    bool $rankRestrict,
    bool $typeRestrict,
    array $expectedNames,
): void {
    updateSetting('pireps.restrict_aircraft_to_rank', $rankRestrict);
    updateSetting('pireps.restrict_aircraft_to_typerating', $typeRestrict);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user] = seedAccessFixture();

    $names = $user->allowedSubfleets()->pluck('name')->sort()->values()->all();

    expect($names)->toEqual($expectedNames);
})->with('access matrix');

it('applies airport restriction only when a flight context is provided', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', true);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user, 'flight' => $flight] = seedAccessFixture();

    // No flight: all 4 aircraft visible
    $idsNoFlight = $user->allowedAircraft()->pluck('aircraft.id')->all();
    expect($idsNoFlight)->toHaveCount(4);

    // With flight departing KJFK: AC-B (at KLAX) excluded; AC-A, AC-C, AC-D included
    $idsWithFlight = $user->allowedAircraft($flight)->pluck('aircraft.id')->all();
    expect($idsWithFlight)->toHaveCount(3);
});

it('does not apply airport restriction when the setting is off', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user, 'flight' => $flight] = seedAccessFixture();

    expect($user->allowedAircraft($flight)->count())->toEqual(4);
});

it('excludes aircraft bid by another user when bid-block is on', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', true);

    ['user' => $user, 'flight' => $flight, 'aircraftA' => $aircraftA] = seedAccessFixture();

    $otherUser = User::factory()->create();
    Bid::create([
        'user_id'     => $otherUser->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $aircraftA->id,
    ]);

    $ids = $user->allowedAircraft()->pluck('aircraft.id')->all();
    expect($ids)->not->toContain($aircraftA->id)
        ->and($ids)->toHaveCount(3);
});

it('includes the requesting user own bid', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', true);

    ['user' => $user, 'flight' => $flight, 'aircraftA' => $aircraftA] = seedAccessFixture();

    Bid::create([
        'user_id'     => $user->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $aircraftA->id,
    ]);

    $ids = $user->allowedAircraft()->pluck('aircraft.id')->all();
    expect($ids)->toContain($aircraftA->id);
});

it('ignores bid block when the setting is off', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user, 'flight' => $flight, 'aircraftA' => $aircraftA] = seedAccessFixture();

    $otherUser = User::factory()->create();
    Bid::create([
        'user_id'     => $otherUser->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $aircraftA->id,
    ]);

    expect($user->allowedAircraft()->count())->toEqual(4);
});

it('combines flight-pinned subfleets via intersection with user access', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user, 'flight' => $flight, 'sfA' => $sfA, 'sfC' => $sfC] = seedAccessFixture();

    // Flight pinned to A + C; user (rank) allows A + B
    $flight->subfleets()->attach([$sfA->id, $sfC->id]);

    // Intersection: only A
    $names = Subfleet::query()
        ->allowedFor($user)
        ->whereHas('flights', fn ($q) => $q->whereKey($flight->id))
        ->pluck('name')->all();

    expect($names)->toEqual(['A']);
});
