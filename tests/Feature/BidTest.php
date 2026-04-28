<?php

use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Models\Bid;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\BidService;
use App\Services\FareService;
use App\Services\FlightService;

test('bids', function () {
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.disable_flight_on_bid', false);

    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $fareSvc = app(FareService::class);
    $bidSvc = app(BidService::class);

    $fare = Fare::factory()->create();
    $fareSvc->setForSubfleet($subfleet, $fare, [
        'price' => 50, 'capacity' => 400,
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'flight_time' => 1000,
        'rank_id'     => $rank->id,
    ]);

    apiAs($user);

    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id' => $user->airline_id,
    ]);

    $bid = $bidSvc->addBid($flight, $user, $subfleet->aircraft->first());
    expect($bid->user_id)->toEqual($user->id)
        ->and($bid->flight_id)->toEqual($flight->id)
        ->and($flight->has_bid)->toBeTrue();

    $flight = $bid->flight;

    // Refresh
    $flight = Flight::find($flight->id);
    expect($flight->has_bid)->toBeTrue();

    // Check the table and make sure the entry is there
    $bid_retrieved = $bidSvc->addBid($flight, $user);
    expect($bid_retrieved->id)->toEqual($bid->id);

    $user->refresh();
    $bids = $user->bids;
    expect($bids->count())->toEqual(1);

    // Query the API and see that the user has the bids
    // And pull the flight details for the user/bids
    $req = $this->get('/api/user/bids');

    $body = $req->json()['data'];
    $req->assertStatus(200);
    expect($body[0]['flight_id'])->toEqual($flight->id)
        ->and($body[0]['flight']['subfleets'])->not->toBeNull()
        ->and($body[0]['flight']['subfleets'][0]['fares'])->not->toBeNull();

    // Make sure subfleets and fares are included

    $req = $this->get('/api/users/'.$user->id.'/bids');

    $body = $req->json()['data'];
    $req->assertStatus(200);
    expect($body[0]['flight_id'])->toEqual($flight->id);

    // have a second user bid on it
    $user2 = User::factory()->create(['rank_id' => $rank->id]);

    $bid_user2 = $bidSvc->addBid($flight, $user2);
    expect($bid_user2)->not->toBeNull()
        ->and($bid_retrieved->id)->not->toEqual($bid_user2->id);

    // Now remove the flight and check API
    $bidSvc->removeBid($flight, $user);

    $flight = Flight::find($flight->id);

    // user2 still has a bid on it
    expect($flight->has_bid)->toBeTrue();

    // Remove it from 2nd user
    $bidSvc->removeBid($flight, $user2);
    $flight->refresh();
    expect($flight->has_bid)->toBeFalse();

    $user->refresh();
    $bids = $user->bids()->get();
    expect($bids->isEmpty())->toBeTrue();

    $req = $this->get('/api/user/bids');
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body)->toHaveCount(0);

    $req = $this->get('/api/users/'.$user->id.'/bids');
    $req->assertStatus(200);
    $body = $req->json()['data'];
    expect($body)->toHaveCount(0);
});

test('multiple bids single flight', function () {
    updateSetting('bids.disable_flight_on_bid', true);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create([
        'airline_id' => $user1->airline_id,
    ]);

    $flight = Flight::factory()->create([
        'airline_id' => $user1->airline_id,
    ]);

    $bidSvc = app(BidService::class);

    // Put bid on the flight to block it off
    $bidSvc->addBid($flight, $user1);

    // Try adding again, should throw an exception
    $bidSvc->addBid($flight, $user2);
})->throws(BidExistsForFlight::class);

test('add bid api', function () {
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    apiAs($user);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $uri = '/api/user/bids';
    $data = ['flight_id' => $flight->id];

    $body = $this->put($uri, $data);
    $body = $body->json('data');

    expect($flight->id)->toEqual($body['flight_id'])
        ->and($body['flight'])->not->toBeEmpty();

    $res = $this->get('/api/bids/'.$body['id']);
    $res->assertOk();

    $body = $res->json('data');
    expect($flight->id)->toEqual($body['flight_id'])
        ->and($body['flight'])->not->toBeEmpty();

    // Now try to have the second user bid on it
    // Should return a 409 error
    $response = $this->withHeader('Authorization', $user2->api_key)->put($uri, $data);
    $response->assertStatus(409);

    // Try now deleting the bid from the user
    $response = $this->delete($uri, $data);
    $body = $response->json('data');
    expect($body)->toHaveCount(0);
});

test('add bid api returns not found for missing flight', function () {
    $user = User::factory()->create();
    apiAs($user);

    $this->put('/api/user/bids', ['flight_id' => 'INVALID'])
        ->assertNotFound();
});

test('delete bid api returns not found for missing flight', function () {
    $user = User::factory()->create();
    apiAs($user);

    $this->delete('/api/user/bids', ['flight_id' => 'INVALID'])
        ->assertNotFound();
});

test('delete flight with bids', function () {
    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $bidSvc = app(BidService::class);

    $bid = $bidSvc->addBid($flight, $user);
    expect($bid->user_id)->toEqual($user->id)
        ->and($bid->flight_id)->toEqual($flight->id)
        ->and($flight->has_bid)->toBeTrue();

    app(FlightService::class)->deleteFlight($flight);

    $empty_flight = Flight::find($flight->id);
    expect($empty_flight)->toBeNull();

    // Make sure no bids exist
    $user_bids_count = Bid::where(['flight_id' => $flight->id])->count();
    expect($user_bids_count)->toEqual(0);

    // Query the API and see that the user has the bids
    // And pull the flight details for the user/bids
    $req = $this->get('/api/user/bids');
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body)->toHaveCount(0);
    updateSetting('bids.allow_multiple_bids', true);

    $req = $this->get('/api/users/'.$user->id.'/bids');
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body)->toHaveCount(0);
});

test('bid with aircraft', function () {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.block_aircraft', true);

    $user = User::factory()->create();
    apiAs($user);
    $bidSvc = app(BidService::class);

    $subfleet_unused = Subfleet::factory()->hasAircraft(10)->create();
    $subfleet = Subfleet::factory()->hasAircraft(10)->create();

    $aircraft = $subfleet->aircraft->first();

    $flight = Flight::factory()->hasAttached([$subfleet, $subfleet_unused])->create([
        'airline_id' => $user->airline_id,
    ]);

    $bid = $bidSvc->addBid($flight, $user, $aircraft);
    $bid_flight = $bid->flight;
    $aircraft->loadMissing('bid');
    expect($bid_flight->subfleets[0]->aircraft->count())->toEqual(1)
        ->and($bid->user_id)->toEqual($user->id)
        ->and($bid->flight_id)->toEqual($flight->id)
        ->and($bid->aircraft_id)->toEqual($aircraft->id)
        ->and($flight->has_bid)->toBeTrue()
        ->and($aircraft->bid->count())->toEqual(1);

    // Expect aircraft to have a bid

    // Now add another bid on another flight with the same aircraft, should throw an exception
    $flight2 = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id' => $user->airline_id,
    ]);

    $bid2 = $bidSvc->addBid($flight2, $user, $aircraft);

    // Remove the first one and try again
    $bidSvc->removeBid($flight, $user);

    $bid2 = $bidSvc->addBid($flight2, $user, $aircraft);
    expect($bid2->user_id)->toEqual($user->id)
        ->and($bid2->flight_id)->toEqual($flight2->id)
        ->and($bid2->aircraft_id)->toEqual($aircraft->id)
        ->and($flight2->has_bid)->toBeTrue();
})->throws(BidExistsForAircraft::class);
