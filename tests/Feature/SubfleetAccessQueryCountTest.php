<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pins the query count for the access-control hot paths. The point isn't the
 * exact number — it's that the count is O(1) wrt result-set size. If you change
 * the policy / scopes and these numbers drift, decide whether the new shape is
 * still constant-time before bumping the ceiling.
 */
function seedPagedFlights(int $count): array
{
    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();

    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->create();
    $rank->subfleets()->attach($subfleet->id);

    $user = User::factory()->create([
        'rank_id'    => $rank->id,
        'airline_id' => $airline->id,
    ]);

    $flights = collect();
    for ($i = 0; $i < $count; $i++) {
        $f = Flight::factory()->create([
            'airline_id'     => $airline->id,
            'dpt_airport_id' => $airport->id,
            'flight_number'  => 1000 + $i,
        ]);
        $f->subfleets()->attach($subfleet->id);
        $flights->push($f);
    }

    return ['user' => $user, 'flights' => $flights, 'subfleet' => $subfleet, 'airline' => $airline];
}

it('runs O(1) queries when eager-loading accessible subfleets across many flights', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user] = seedPagedFlights(25);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $page = Flight::query()
        ->withAccessibleSubfleets($user)
        ->paginate(25);

    // Force iteration so eager-loads fire
    $page->each(fn (Flight $f) => $f->subfleets->each(fn ($s) => $s->aircraft));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // What's load-bearing is O(1) wrt row count — not the exact integer. A 25-flight
    // page produces a constant set of queries (paginate count + select, plus eager
    // loads of subfleets + aircraft + their pivots, plus the BelongsToThrough
    // airline join the Aircraft model carries). Ceiling 16 keeps the test
    // sensitive to a regression to N+1 (which would be 25+ on this fixture)
    // without being brittle to Eloquent internals.
    expect($queryCount)->toBeLessThanOrEqual(16);
});

it('runs O(1) queries for findBidsForUser-equivalent flow', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', false);

    ['user' => $user, 'flights' => $flights, 'subfleet' => $subfleet] = seedPagedFlights(20);

    // Create 20 bids for this user, one per flight
    $aircraftId = $subfleet->aircraft->first()->id;
    foreach ($flights as $f) {
        Bid::create([
            'user_id'     => $user->id,
            'flight_id'   => $f->id,
            'aircraft_id' => $aircraftId,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $bids = Bid::query()
        ->where('user_id', $user->id)
        ->with([
            'flight' => fn ($q) => $q->withAccessibleSubfleets($user),
        ])
        ->get();

    $bids->each(fn (Bid $b) => $b->flight?->subfleets->each(fn ($s) => $s->aircraft));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(16)
        ->and($bids->count())->toBe(20);
});
