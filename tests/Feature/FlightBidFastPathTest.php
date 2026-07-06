<?php

use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;

// ---------------------------------------------------------------------------
// get() with=bid
// ---------------------------------------------------------------------------

test('get with bid token returns only bid subfleet and reconciled fares', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $fareSvc = app(FareService::class);

    $user = User::factory()->create();
    apiAs($user);

    $subfleet = Subfleet::factory()->create();
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    $fare = Fare::factory()->create();
    // Subfleet-level fare override — this is what the API returns (SubfleetResource applies
    // subfleet pivot; flight-level overrides are recomputed at PIREP-file time).
    $fareSvc->setForSubfleet($subfleet, $fare, ['price' => 100, 'capacity' => 50]);

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);
    $flight->subfleets()->attach($subfleet->id);

    Bid::create([
        'user_id'     => $user->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $aircraft->id,
    ]);

    $res = $this->get('/api/flights/'.$flight->id.'?with=bid');
    $res->assertStatus(200);

    $body = $res->json()['data'];
    // Subfleet is the bid's subfleet; fare is present with the subfleet-level price.
    expect($body['subfleets'])->toHaveCount(1)
        ->and($body['subfleets'][0]['id'])->toBe($subfleet->id)
        ->and($body['subfleets'][0]['fares'])->toHaveCount(1)
        ->and((float) $body['subfleets'][0]['fares'][0]['price'])->toEqual(100.0);
});

test('get with bid token returns empty subfleets when pilot has no bid', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);

    $res = $this->get('/api/flights/'.$flight->id.'?with=bid');
    $res->assertStatus(200);

    expect($res->json()['data']['subfleets'])->toBeEmpty();
});

test('get with bid token only shows the authenticated pilots own bid subfleet', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $pilotA = User::factory()->create();
    $pilotB = User::factory()->create();

    $subfleetA = Subfleet::factory()->create();
    $aircraftA = Aircraft::factory()->create(['subfleet_id' => $subfleetA->id]);

    $subfleetB = Subfleet::factory()->create();
    $aircraftB = Aircraft::factory()->create(['subfleet_id' => $subfleetB->id]);

    $flight = Flight::factory()->create(['airline_id' => $pilotA->airline_id]);

    Bid::create(['user_id' => $pilotA->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraftA->id]);
    Bid::create(['user_id' => $pilotB->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraftB->id]);

    apiAs($pilotA);

    $res = $this->get('/api/flights/'.$flight->id.'?with=bid');
    $res->assertStatus(200);

    $subfleets = $res->json()['data']['subfleets'];
    expect($subfleets)->toHaveCount(1)
        ->and($subfleets[0]['id'])->toBe($subfleetA->id);
});

test('get without bid token performs full accessible fleet expansion', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $user = User::factory()->create();
    apiAs($user);

    $subfleet1 = Subfleet::factory()->create();
    $subfleet2 = Subfleet::factory()->create();

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);
    $flight->subfleets()->attach([$subfleet1->id, $subfleet2->id]);

    $res = $this->get('/api/flights/'.$flight->id);
    $res->assertStatus(200);

    // Both pinned subfleets returned via accessibleSubfleetsFor (fleet expansion path)
    expect($res->json()['data']['subfleets'])->toHaveCount(2);
});

test('get with bid token skips accessible fleet expansion (behavioral proof)', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $user = User::factory()->create();
    apiAs($user);

    // Two subfleets both pinned to flight
    $subfleet1 = Subfleet::factory()->create();
    $subfleet2 = Subfleet::factory()->create();
    $aircraft1 = Aircraft::factory()->create(['subfleet_id' => $subfleet1->id]);

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);
    $flight->subfleets()->attach([$subfleet1->id, $subfleet2->id]);

    // Bid only on subfleet1's aircraft
    Bid::create(['user_id' => $user->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraft1->id]);

    $res = $this->get('/api/flights/'.$flight->id.'?with=bid');
    $res->assertStatus(200);

    // Fleet expansion would return 2 subfleets; bid fast-path returns only 1
    $subfleets = $res->json()['data']['subfleets'];
    expect($subfleets)->toHaveCount(1)
        ->and($subfleets[0]['id'])->toBe($subfleet1->id);
});

test('get with bid returns all bid subfleets with eager aircraft and no lazy load', function (): void {
    // Two bids (two aircraft in two subfleets) on one flight. With >= 2 hydrated
    // subfleets, preventLazyLoading (on outside production) would throw if the
    // subfleet's aircraft were not eager-loaded when SubfleetResource reads it.
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $user = User::factory()->create();
    apiAs($user);

    $subfleetA = Subfleet::factory()->create();
    $aircraftA = Aircraft::factory()->create(['subfleet_id' => $subfleetA->id]);
    $subfleetB = Subfleet::factory()->create();
    $aircraftB = Aircraft::factory()->create(['subfleet_id' => $subfleetB->id]);

    $flight = Flight::factory()->create(['airline_id' => $user->airline_id]);

    Bid::create(['user_id' => $user->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraftA->id]);
    Bid::create(['user_id' => $user->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraftB->id]);

    $res = $this->get('/api/flights/'.$flight->id.'?with=bid');
    $res->assertStatus(200);

    $subfleets = collect($res->json()['data']['subfleets']);
    expect($subfleets)->toHaveCount(2)
        ->and($subfleets->pluck('id')->all())->toContain($subfleetA->id, $subfleetB->id)
        // aircraft is eager-loaded and serialized (not lazy-loaded)
        ->and($subfleets->firstWhere('id', $subfleetA->id)['aircraft'])->toHaveCount(1)
        ->and($subfleets->firstWhere('id', $subfleetB->id)['aircraft'])->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// search() with=bid
// ---------------------------------------------------------------------------

test('search with bid token decorates bid flights with subfleet and empty for non-bid flights', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_flights_from_current', false);

    $user = User::factory()->create();
    apiAs($user);

    $subfleet = Subfleet::factory()->create();
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    $flight1 = Flight::factory()->create(['airline_id' => $user->airline_id]);
    $flight2 = Flight::factory()->create(['airline_id' => $user->airline_id]);

    Bid::create(['user_id' => $user->id, 'flight_id' => $flight1->id, 'aircraft_id' => $aircraft->id]);

    $res = $this->get('/api/flights/search?with=bid');
    $res->assertStatus(200);

    $data = collect($res->json()['data']);

    $f1 = $data->firstWhere('id', $flight1->id);
    $f2 = $data->firstWhere('id', $flight2->id);

    expect($f1)->not()->toBeNull()
        ->and($f1['subfleets'])->toHaveCount(1)
        ->and($f1['subfleets'][0]['id'])->toBe($subfleet->id);

    expect($f2)->not()->toBeNull()
        ->and($f2['subfleets'])->toBeEmpty();
});

// ---------------------------------------------------------------------------
// By-id search visibility
// ---------------------------------------------------------------------------

test('search with flight_id returns invisible flight', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
        'visible'    => false,
    ]);

    $res = $this->get('/api/flights/search?flight_id='.$flight->id);
    $res->assertStatus(200);

    $data = $res->json()['data'];
    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($flight->id);
});

test('browse search without flight_id hides invisible flights', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_flights_from_current', false);

    $user = User::factory()->create();
    apiAs($user);

    $visible = Flight::factory()->create(['airline_id' => $user->airline_id, 'visible' => true]);
    $invisible = Flight::factory()->create(['airline_id' => $user->airline_id, 'visible' => false]);

    $res = $this->get('/api/flights/search');
    $res->assertStatus(200);

    $ids = collect($res->json()['data'])->pluck('id');
    expect($ids)->toContain($visible->id)
        ->and($ids)->not()->toContain($invisible->id);
});

test('search by flight_id with bid token returns invisible flight with bid subfleets', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $user = User::factory()->create();
    apiAs($user);

    $subfleet = Subfleet::factory()->create();
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
        'visible'    => false,
    ]);

    Bid::create(['user_id' => $user->id, 'flight_id' => $flight->id, 'aircraft_id' => $aircraft->id]);

    $res = $this->get('/api/flights/search?flight_id='.$flight->id.'&with=bid');
    $res->assertStatus(200);

    $data = $res->json()['data'];
    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($flight->id)
        ->and($data[0]['subfleets'])->toHaveCount(1)
        ->and($data[0]['subfleets'][0]['id'])->toBe($subfleet->id);
});
