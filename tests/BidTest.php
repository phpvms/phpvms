<?php

namespace Tests;

use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Models\Bid;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\BidService;
use App\Services\FareService;
use App\Services\FlightService;

final class BidTest extends TestCase
{
    protected BidService $bidSvc;

    protected FlightService $flightSvc;

    protected SettingRepository $settingsRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        $this->bidSvc = app(BidService::class);
        $this->flightSvc = app(FlightService::class);
        $this->settingsRepo = app(SettingRepository::class);
    }

    /**
     * Add/remove a bid, test the API, etc
     *
     * @throws \Exception
     */
    public function test_bids(): void
    {
        $this->settingsRepo->store('bids.allow_multiple_bids', true);
        $this->settingsRepo->store('bids.disable_flight_on_bid', false);

        /** @var Subfleet $subfleet */
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(2, [$subfleet['subfleet']->id]);

        /** @var FareService $fare_svc */
        $fare_svc = app(FareService::class);

        /** @var Fare $fare */
        $fare = Fare::factory()->create();
        $fare_svc->setForSubfleet($subfleet['subfleet'], $fare, [
            'price' => 50, 'capacity' => 400,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'flight_time' => 1000,
            'rank_id'     => $rank->id,
        ]);

        $headers = $this->headers($user);

        /** @var Flight $flight */
        $flight = $this->addFlight($user, [], $subfleet['subfleet']->id);

        $bid = $this->bidSvc->addBid($flight, $user, $subfleet['aircraft'][0]);
        $this->assertEquals($user->id, $bid->user_id);
        $this->assertEquals($flight->id, $bid->flight_id);
        $this->assertTrue($flight->has_bid);

        $flight = $bid->flight;

        // Refresh
        $flight = Flight::find($flight->id);
        $this->assertTrue($flight->has_bid);

        // Check the table and make sure the entry is there
        $bid_retrieved = $this->bidSvc->addBid($flight, $user);
        $this->assertEquals($bid->id, $bid_retrieved->id);

        $user->refresh();
        $bids = $user->bids;
        $this->assertEquals(1, $bids->count());

        // Query the API and see that the user has the bids
        // And pull the flight details for the user/bids
        $req = $this->get('/api/user/bids', $headers);

        $body = $req->json()['data'];
        $req->assertStatus(200);
        $this->assertEquals($flight->id, $body[0]['flight_id']);

        // Make sure subfleets and fares are included
        $this->assertNotNull($body[0]['flight']['subfleets']);
        $this->assertNotNull($body[0]['flight']['subfleets'][0]['fares']);

        $req = $this->get('/api/users/'.$user->id.'/bids', $headers);

        $body = $req->json()['data'];
        $req->assertStatus(200);
        $this->assertEquals($flight->id, $body[0]['flight_id']);

        // have a second user bid on it

        /** @var User $user */
        $user2 = User::factory()->create(['rank_id' => $rank->id]);

        $bid_user2 = $this->bidSvc->addBid($flight, $user2);
        $this->assertNotNull($bid_user2);
        $this->assertNotEquals($bid_retrieved->id, $bid_user2->id);

        // Now remove the flight and check API

        $this->bidSvc->removeBid($flight, $user);

        $flight = Flight::find($flight->id);

        // user2 still has a bid on it
        $this->assertTrue($flight->has_bid);

        // Remove it from 2nd user
        $this->bidSvc->removeBid($flight, $user2);
        $flight->refresh();
        $this->assertFalse($flight->has_bid);

        $user->refresh();
        $bids = $user->bids()->get();
        $this->assertTrue($bids->isEmpty());

        $req = $this->get('/api/user/bids', $headers);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertCount(0, $body);

        $req = $this->get('/api/users/'.$user->id.'/bids', $headers);
        $req->assertStatus(200);
        $body = $req->json()['data'];
        $this->assertCount(0, $body);
    }

    public function test_multiple_bids_single_flight(): void
    {
        $this->settingsRepo->store('bids.disable_flight_on_bid', true);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create([
            'airline_id' => $user1->airline_id,
        ]);

        $flight = $this->addFlight($user1);

        // Put bid on the flight to block it off
        $this->bidSvc->addBid($flight, $user1);

        // Try adding again, should throw an exception
        $this->expectException(BidExistsForFlight::class);
        $this->bidSvc->addBid($flight, $user2);
    }

    /**
     * Add a flight bid VIA the API
     */
    public function test_add_bid_api(): void
    {
        $this->user = User::factory()->create();
        $user2 = User::factory()->create();

        /** @var \App\Models\Flight $flight */
        $flight = $this->addFlight($this->user);

        $uri = '/api/user/bids';
        $data = ['flight_id' => $flight->id];

        $body = $this->put($uri, $data);
        $body = $body->json('data');

        $this->assertEquals($body['flight_id'], $flight->id);
        $this->assertNotEmpty($body['flight']);

        $res = $this->get('/api/bids/'.$body['id']);
        $res->assertOk();

        $body = $res->json('data');
        $this->assertEquals($body['flight_id'], $flight->id);
        $this->assertNotEmpty($body['flight']);

        // Now try to have the second user bid on it
        // Should return a 409 error
        $response = $this->put($uri, $data, [], $user2);
        $response->assertStatus(409);

        // Try now deleting the bid from the user
        $response = $this->delete($uri, $data);
        $body = $response->json('data');
        $this->assertCount(0, $body);
    }

    /**
     * Delete a flight and make sure all the bids are gone
     *
     * @throws \Exception
     */
    public function test_delete_flight_with_bids(): void
    {
        $user = User::factory()->create();
        $headers = $this->headers($user);

        $flight = $this->addFlight($user);

        $bid = $this->bidSvc->addBid($flight, $user);
        $this->assertEquals($user->id, $bid->user_id);
        $this->assertEquals($flight->id, $bid->flight_id);
        $this->assertTrue($flight->has_bid);

        $this->flightSvc->deleteFlight($flight);

        $empty_flight = Flight::find($flight->id);
        $this->assertNull($empty_flight);

        // Make sure no bids exist
        $user_bids_count = Bid::where(['flight_id' => $flight->id])->count();
        $this->assertEquals(0, $user_bids_count);

        // Query the API and see that the user has the bids
        // And pull the flight details for the user/bids
        $req = $this->get('/api/user/bids', $headers);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertCount(0, $body);
        $this->settingsRepo->store('bids.allow_multiple_bids', true);

        $req = $this->get('/api/users/'.$user->id.'/bids', $headers);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertCount(0, $body);
    }

    /**
     * Create a bid with an aircraft and check the aircraft booking
     *
     * @throws \Exception
     */
    public function test_bid_with_aircraft(): void
    {
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);
        $this->settingsRepo->store('pireps.only_aircraft_at_dpt_airport', false);
        $this->settingsRepo->store('bids.allow_multiple_bids', true);
        $this->settingsRepo->store('bids.block_aircraft', true);

        $user = User::factory()->create();
        $headers = $this->headers($user);

        $subfleet_unused = $this->createSubfleetWithAircraft(10);
        $subfleet = $this->createSubfleetWithAircraft(10);
        $aircraft = $subfleet['aircraft']->first();

        $flight = $this->addFlight($user, [], $subfleet['subfleet']->id);
        $flight->subfleets()->syncWithoutDetaching([$subfleet_unused['subfleet']->id]);

        $bid = $this->bidSvc->addBid($flight, $user, $aircraft);
        $bid_flight = $bid->flight;
        $this->assertEquals(1, $bid_flight->subfleets[0]->aircraft->count());

        $this->assertEquals($user->id, $bid->user_id);
        $this->assertEquals($flight->id, $bid->flight_id);
        $this->assertEquals($aircraft->id, $bid->aircraft_id);
        $this->assertTrue($flight->has_bid);

        // Expect aircraft to have a bid
        $this->assertEquals(1, $aircraft->bid->count());

        // Now add another bid on another flight with the same aircraft, should throw an exception
        $flight2 = $this->addFlight($user, [], $subfleet['subfleet']->id);

        $this->expectException(BidExistsForAircraft::class);
        $bid2 = $this->bidSvc->addBid($flight2, $user, $aircraft);

        // Remove the first one and try again
        $this->bidSvc->removeBid($flight, $user);

        $bid2 = $this->bidSvc->addBid($flight2, $user, $aircraft);
        $this->assertEquals($user->id, $bid2->user_id);
        $this->assertEquals($flight2->id, $bid2->flight_id);
        $this->assertEquals($aircraft->id, $bid2->aircraft_id);
        $this->assertTrue($flight2->has_bid);
    }
}
