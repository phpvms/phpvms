<?php

use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;

/**
 * Reset the request-scoped setting memo so a measured request starts cold, as
 * it would under Octane (which flushes the singleton per request). Without this
 * the memo warms across in-test requests and masks the query-count comparison.
 */
function freshRequestState(): void
{
    app(SettingService::class)->clearMemo();
    DB::connection()->flushQueryLog();
}

// Task 4 — end-to-end verification that the `with=bid` fast-path cost is O(bids)
// and independent of how many subfleets the flight/pilot could otherwise expand.

/**
 * Build a flight the pilot has a bid on, plus `$extraSubfleets` additional
 * pinned subfleets (each with an aircraft) that the legacy accessible-fleet
 * expansion would hydrate. The fast-path must ignore these.
 */
function buildBidFlight(User $user, int $extraSubfleets): Flight
{
    $subfleet = Subfleet::factory()->create();
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);
    $flight->subfleets()->attach($subfleet->id);

    for ($i = 0; $i < $extraSubfleets; $i++) {
        $extra = Subfleet::factory()->create();
        Aircraft::factory()->create(['subfleet_id' => $extra->id]);
        $flight->subfleets()->attach($extra->id);
    }

    Bid::create([
        'user_id'     => $user->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $aircraft->id,
    ]);

    return $flight;
}

test('get with bid query count is independent of total subfleet count', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $user = User::factory()->create();
    apiAs($user);

    $small = buildBidFlight($user, 1);
    $large = buildBidFlight($user, 25);

    DB::connection()->enableQueryLog();

    freshRequestState();
    $this->get('/api/flights/'.$small->id.'?with=bid')->assertStatus(200);
    $smallCount = count(DB::connection()->getQueryLog());

    freshRequestState();
    $this->get('/api/flights/'.$large->id.'?with=bid')->assertStatus(200);
    $largeCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    // 1 subfleet vs 26 subfleets → identical query count. The fast-path scales
    // with the number of bids, not the size of the accessible fleet.
    expect($largeCount)->toBe($smallCount);
});

test('search by id with bid query count is independent of total subfleet count', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $user = User::factory()->create();
    apiAs($user);

    $small = buildBidFlight($user, 1);
    $large = buildBidFlight($user, 25);

    DB::connection()->enableQueryLog();

    freshRequestState();
    $this->get('/api/flights/search?flight_id='.$small->id.'&with=bid')->assertStatus(200);
    $smallCount = count(DB::connection()->getQueryLog());

    freshRequestState();
    $this->get('/api/flights/search?flight_id='.$large->id.'&with=bid')->assertStatus(200);
    $largeCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

test('browse search with bid resolves all bids in a single batched query', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_flights_from_current', false);

    $user = User::factory()->create();
    apiAs($user);

    // Several flights the pilot has a bid on — the bid lookup must not be an
    // N+1 across the returned page.
    for ($i = 0; $i < 5; $i++) {
        $subfleet = Subfleet::factory()->create();
        $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);
        $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);
        Bid::create(['user_id' => $user->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraft->id]);
    }

    DB::connection()->enableQueryLog();
    freshRequestState();
    $this->get('/api/flights/search?with=bid')->assertStatus(200);
    // Normalize identifier quoting (postgres/sqlite use ", mysql uses `) so the
    // match is dialect-agnostic across the CI matrix.
    $bidQueries = collect(DB::connection()->getQueryLog())
        ->filter(fn (array $q): bool => str_contains(str_replace(['`', '"'], '', (string) $q['query']), 'from bids'))
        ->count();
    DB::connection()->disableQueryLog();

    expect($bidQueries)->toBe(1);
});

test('bid subfleet fare payload matches the legacy get output for that subfleet', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $fareSvc = app(FareService::class);

    $user = User::factory()->create();
    apiAs($user);

    $subfleet = Subfleet::factory()->create();
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    $fare = Fare::factory()->create();
    $fareSvc->setForSubfleet($subfleet, $fare, ['price' => 100, 'capacity' => 50]);

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);
    $flight->subfleets()->attach($subfleet->id);

    Bid::create([
        'user_id'     => $user->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $aircraft->id,
    ]);

    // Legacy path (accessible-fleet expansion) and fast-path must serialize the
    // bid subfleet's fares identically.
    $legacy = $this->get('/api/flights/'.$flight->id)->json('data');
    $fast = $this->get('/api/flights/'.$flight->id.'?with=bid')->json('data');

    $legacySubfleet = collect($legacy['subfleets'])->firstWhere('id', $subfleet->id);
    $fastSubfleet = collect($fast['subfleets'])->firstWhere('id', $subfleet->id);

    expect($fastSubfleet)->not->toBeNull()
        ->and($fastSubfleet['fares'])->toEqual($legacySubfleet['fares']);
});
