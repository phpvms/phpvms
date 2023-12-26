<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\UserState;
use App\Models\Flight;
use App\Models\Navdata;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\User;
use App\Notifications\Messages\Broadcast\PirepDiverted;
use App\Notifications\Messages\Broadcast\PirepPrefiled;
use App\Notifications\Messages\Broadcast\PirepStatusChanged;
use App\Notifications\Messages\PirepAccepted;
use App\Notifications\Messages\PirepFiled;
use App\Repositories\SettingRepository;
use App\Services\AircraftService;
use App\Services\BidService;
use App\Services\FlightService;
use App\Services\PirepService;
use App\Support\Units\Fuel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class PIREPTest extends TestCase
{
    /** @var PirepService */
    protected $pirepSvc;

    /** @var SettingRepository */
    protected $settingsRepo;

    public function setUp(): void
    {
        parent::setUp();
        $this->addData('base');
        $this->addData('fleet');

        $this->pirepSvc = app(PirepService::class);
        $this->settingsRepo = app(SettingRepository::class);
    }

    protected function createNewRoute()
    {
        $route = [];
        $navpoints = Navdata::factory()->count(5)->create();
        foreach ($navpoints as $point) {
            $route[] = $point->id;
        }

        return $route;
    }

    protected function getAcarsRoute($pirep)
    {
        $saved_route = [];
        $route_points = Acars::where(
            ['pirep_id' => $pirep->id, 'type' => AcarsType::ROUTE]
        )->orderBy('order', 'asc')->get();

        foreach ($route_points as $point) {
            $saved_route[] = $point->name;
        }

        return $saved_route;
    }

    /**
     * @throws Exception
     */
    public function testAddPirep()
    {
        $user = User::factory()->create();

        $route = $this->createNewRoute();
        $pirep = Pirep::factory()->create([
            'user_id' => $user->id,
            'route'   => implode(' ', $route),
        ]);

        $pirep = $this->pirepSvc->create($pirep, []);

        try {
            $this->pirepSvc->saveRoute($pirep);
        } catch (Exception $e) {
            throw $e;
        }

        /*
         * Check the initial state info
         */
        $this->assertEquals($pirep->state, PirepState::PENDING);

        /**
         * Now set the PIREP state to ACCEPTED
         */
        $new_pirep_count = $pirep->user->flights + 1;
        $new_flight_time = $pirep->user->flight_time + $pirep->flight_time;

        $this->pirepSvc->changeState($pirep, PirepState::ACCEPTED);
        $this->assertEquals($new_pirep_count, $pirep->pilot->flights);
        $this->assertEquals($new_flight_time, $pirep->pilot->flight_time);
        $this->assertEquals($pirep->arr_airport_id, $pirep->pilot->curr_airport_id);

        // Check the location of the current aircraft
        $this->assertEquals($pirep->aircraft->airport_id, $pirep->arr_airport_id);

        // Also check via API:
        $this->get('/api/fleet/aircraft/'.$pirep->aircraft_id, [], $user)
            ->assertJson(['data' => ['airport_id' => $pirep->arr_airport_id]]);

        // Make sure a notification was sent out to both the user and the admin(s)
        Notification::assertSentTo([$user], PirepAccepted::class);

        // Try cancelling it
        $uri = '/api/pireps/'.$pirep->id.'/cancel';
        $response = $this->put($uri, [], [], $user);
        $response->assertStatus(400);

        // Try updating some data on it
        $uri = '/api/pireps/'.$pirep->id.'/update';
        $response = $this->put($uri, [
            'state' => 'FIL',
        ], [], $user);

        $response->assertStatus(400);

        /**
         * Now go from ACCEPTED to REJECTED
         */
        $new_pirep_count = $pirep->pilot->flights - 1;
        $new_flight_time = $pirep->pilot->flight_time - $pirep->flight_time;
        $this->pirepSvc->changeState($pirep, PirepState::REJECTED);
        $this->assertEquals($new_pirep_count, $pirep->pilot->flights);
        $this->assertEquals($new_flight_time, $pirep->pilot->flight_time);
        $this->assertEquals($pirep->arr_airport_id, $pirep->pilot->curr_airport_id);

        /**
         * Check the ACARS table
         */
        $saved_route = $this->getAcarsRoute($pirep);
        $this->assertEquals($route, $saved_route);

        /**
         * Recreate the route with new options points. Make sure that the
         * old route is erased from the ACARS table and then recreated
         */
        $route = $this->createNewRoute();
        $pirep->route = implode(' ', $route);
        $pirep->save();

        // this should delete the old route from the acars table
        $this->pirepSvc->saveRoute($pirep);

        $saved_route = $this->getAcarsRoute($pirep);
        $this->assertEquals($route, $saved_route);
    }

    /**
     * Make sure the unit conversions look to be proper
     */
    public function testUnitFields()
    {
        /** @var Pirep $pirep */
        $pirep = $this->createPirep();
        $pirep->save();

        $uri = '/api/pireps/'.$pirep->id;

        $response = $this->get($uri);
        $body = $response->json('data');

        // Check that it has the fuel units
        $this->assertHasKeys($body['block_fuel'], ['lbs', 'kg']);
        $this->assertEquals(
            round($pirep->block_fuel->toUnit('lbs')),
            round($body['block_fuel']['lbs'])
        );

        $this->assertHasKeys($body['fuel_used'], ['lbs', 'kg']);
        $this->assertEquals(
            round($pirep->fuel_used->toUnit('lbs')),
            round($body['fuel_used']['lbs'])
        );

        // Check that it has the distance units
        $this->assertHasKeys($body['distance'], ['km', 'nmi', 'mi']);
        $this->assertEquals(
            round($pirep->distance->toUnit('nmi')),
            round($body['distance']['nmi'])
        );

        // Check the planned_distance field
        $this->assertHasKeys($body['planned_distance'], ['km', 'nmi', 'mi']);
        $this->assertEquals(
            round($pirep->planned_distance->toUnit('nmi')),
            round($body['planned_distance']['nmi'])
        );

        //Check conversion on save
        $val = random_int(1000, 9999999);
        $pirep->block_fuel = $val;
        $pirep->fuel_used = $val;

        // no conversion with plain numbers
        $this->assertEquals($pirep->block_fuel->internal(2), $val);
        $this->assertEquals($pirep->fuel_used->internal(2), $val);

        // no conversion with lbs
        $pirep->block_fuel = new Fuel($val, 'lbs');
        $this->assertEquals($pirep->block_fuel->internal(2), round($val, 2));

        $pirep->fuel_used = new Fuel($val, 'lbs');
        $this->assertEquals($pirep->fuel_used->internal(2), round($val, 2));

        // conversion of kg to lbs
        $pirep->block_fuel = new Fuel($val, 'kg');
        $this->assertEquals(
            $pirep->block_fuel->internal(2),
            Fuel::make($val, 'kg')->toUnit('lbs', 2)
        );

        $pirep->fuel_used = new Fuel($val, 'kg');
        $this->assertEquals(
            $pirep->fuel_used->internal(2),
            Fuel::make($val, 'kg')->toUnit('lbs', 2)
        );
    }

    public function testGetUserPireps()
    {
        $this->user = User::factory()->create();
        $pirep_done = Pirep::factory()->create([
            'user_id' => $this->user->id,
            'state'   => PirepState::ACCEPTED,
        ]);

        $pirep_in_progress = Pirep::factory()->create([
            'user_id' => $this->user->id,
            'state'   => PirepState::IN_PROGRESS,
        ]);

        $pirep_cancelled = Pirep::factory()->create([
            'user_id' => $this->user->id,
            'state'   => PirepState::CANCELLED,
        ]);

        $pireps = $this->get('/api/user/pireps')
            ->assertStatus(200)
            ->json();

        $pirep_ids = collect($pireps['data'])->pluck('id');

        $this->assertTrue($pirep_ids->contains($pirep_done->id));
        $this->assertTrue($pirep_ids->contains($pirep_in_progress->id));
        $this->assertFalse($pirep_ids->contains($pirep_cancelled->id));

        // Get only status
        $pireps = $this->get('/api/user/pireps?state='.PirepState::IN_PROGRESS)
            ->assertStatus(200)
            ->json();

        $pirep_ids = collect($pireps['data'])->pluck('id');
        $this->assertTrue($pirep_ids->contains($pirep_in_progress->id));
        $this->assertFalse($pirep_ids->contains($pirep_done->id));
        $this->assertFalse($pirep_ids->contains($pirep_cancelled->id));
    }

    /**
     * Make sure that a notification has been sent out to admins when a PIREP is submitted
     *
     * @throws \Exception
     */
    public function testPirepNotifications()
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name'        => 'testPirepNotifications user',
            'flights'     => 0,
            'flight_time' => 0,
            'rank_id'     => 1,
        ]);

        $admin = $this->createAdminUser(['name' => 'testPirepNotifications Admin']);

        $pirep = Pirep::factory()->create([
            'airline_id' => 1,
            'user_id'    => $user->id,
        ]);

        $this->pirepSvc->create($pirep);
        $this->pirepSvc->submit($pirep);

        // Make sure a notification was sent out to the admin
        Notification::assertSentTo([$admin], PirepFiled::class);
        Notification::assertNotSentTo([$user], PirepFiled::class);
    }

    /**
     * check the stats/ranks, etc have incremented properly
     */
    public function testPilotStatsIncr()
    {
        $this->updateSetting('pilots.count_transfer_hours', false);

        /** @var User $user */
        $user = User::factory()->create([
            'flights'     => 0,
            'flight_time' => 0,
            'rank_id'     => 1,
        ]);

        // Submit two PIREPs
        $pireps = Pirep::factory()->count(2)->create([
            'airline_id'  => $user->airline_id,
            'aircraft_id' => 1,
            'user_id'     => $user->id,
            // 360min == 6 hours, rank should bump up
            'flight_time' => 360,
        ]);

        $aircraft = Aircraft::find(1);
        $flight_time_initial = $aircraft->flight_time;

        foreach ($pireps as $pirep) {
            $this->pirepSvc->create($pirep);
            $this->pirepSvc->accept($pirep);
        }

        /** @var User $pilot */
        $pilot = User::find($user->id);
        $last_pirep = Pirep::where('id', $pilot->last_pirep_id)->first();

        // Make sure rank went up
        $this->assertGreaterThan($user->rank_id, $pilot->rank_id);
        $this->assertEquals($last_pirep->arr_airport_id, $pilot->curr_airport_id);
        $this->assertEquals(2, $pilot->flights);

        $aircraft = Aircraft::find(1);
        $after_time = $flight_time_initial + 720;
        $this->assertEquals($after_time, $aircraft->flight_time);

        //
        // Submit another PIREP, adding another 6 hours
        // it should automatically be accepted
        //
        $pirep = Pirep::factory()->create([
            'airline_id' => 1,
            'user_id'    => $user->id,
            // 120min == 2 hours, currently at 9 hours
            // Rank bumps up at 10 hours
            'flight_time' => 120,
        ]);

        // Pilot should be at rank 2, where accept should be automatic
        $this->pirepSvc->create($pirep);
        $this->pirepSvc->submit($pirep);

        $pilot->refresh();

        $this->assertEquals(3, $pilot->flights);

        $latest_pirep = Pirep::where('id', $pilot->last_pirep_id)->first();

        // Make sure PIREP was auto updated
        $this->assertEquals(PirepState::ACCEPTED, $latest_pirep->state);

        // Make sure latest PIREP was updated
        $this->assertNotEquals($last_pirep->id, $latest_pirep->id);
    }

    /**
     * Assign a rank to a user which is not set to auto-promote; make that that the flight
     * hours and submitted PIREP doesn't change the rank
     *
     * @throws \Exception
     */
    public function testPilotDontChangeRank()
    {
        /** @var Rank $rank */
        $rank = Rank::factory()->create([
            'hours'        => 15,
            'auto_promote' => false,
        ]);

        // Set the user to the above rank, non-promote, they shouldn't bump down

        /** @var User $user */
        $user = User::factory()->create([
            'flights'     => 0,
            'flight_time' => 0,
            'rank_id'     => $rank->id,
        ]);

        // Submit two PIREPs
        $pirep = Pirep::factory()->create([
            'airline_id'  => $user->airline_id,
            'aircraft_id' => 1,
            'user_id'     => $user->id,
            'flight_time' => 10 * 60, // 10 hours, eligible for Junior First Officer
        ]);

        $this->pirepSvc->create($pirep);
        $this->pirepSvc->accept($pirep);

        $pilot = User::find($user->id);

        // Make sure rank didn't change
        $this->assertEquals($rank->id, $pilot->rank_id);
    }

    /**
     * check the stats/ranks, etc have incremented properly
     */
    public function testPilotStatsIncrWithTransferHours()
    {
        $this->updateSetting('pilots.count_transfer_hours', true);

        $user = User::factory()->create([
            'flights'       => 0,
            'flight_time'   => 0,
            'transfer_time' => 720,
            'rank_id'       => 1,
        ]);

        // Submit two PIREPs
        // 1 hour flight times, but the rank should bump up because of the transfer hours
        $pireps = Pirep::factory()->count(2)->create([
            'airline_id'  => $user->airline_id,
            'aircraft_id' => 1,
            'user_id'     => $user->id,
            'flight_time' => 60,
        ]);

        foreach ($pireps as $pirep) {
            $this->pirepSvc->create($pirep);
            $this->pirepSvc->accept($pirep);
        }

        $pilot = User::find($user->id);
        $last_pirep = $pilot->last_pirep;
        $this->assertEquals($pilot->last_pirep_id, $last_pirep->id);

        // Make sure rank went up
        $this->assertGreaterThan($user->rank_id, $pilot->rank_id);

        // Check the aircraft
        $aircraft = Aircraft::where('id', 1)->first();
        $this->assertEquals(120, $aircraft->flight_time);

        // Reset the aircraft flight time
        $aircraft->flight_time = 10;
        $aircraft->save();

        // Recalculate the status
        /** @var AircraftService $aircraftSvc */
        $aircraftSvc = app(AircraftService::class);
        $aircraftSvc->recalculateStats();

        $aircraft = Aircraft::where('id', 1)->first();
        $this->assertEquals(120, $aircraft->flight_time);
    }

    /**
     * When a PIREP is filed by a user on leave, make sure they flip from leave to active
     * It doesn't matter if the PIREP is accepted or rejected
     */
    public function testPilotStatusChange()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'state' => UserState::ON_LEAVE,
        ]);

        // Submit two PIREPs
        // 1 hour flight times, but the rank should bump up because of the transfer hours
        /** @var Pirep $pirep */
        $pirep = Pirep::factory()->create([
            'airline_id' => $user->airline_id,
            'user_id'    => $user->id,
        ]);

        $this->pirepSvc->create($pirep);
        $this->pirepSvc->submit($pirep);

        /** @var User $user */
        $user = User::find($user->id);
        $this->assertEquals(UserState::ACTIVE, $user->state);
    }

    /**
     * Find and check for any duplicate PIREPs by a user
     */
    public function testDuplicatePireps()
    {
        $user = User::factory()->create();
        $pirep = Pirep::factory()->create([
            'user_id' => $user->id,
        ]);

        // This should find itself...
        $dupe_pirep = $this->pirepSvc->findDuplicate($pirep);
        $this->assertNotFalse($dupe_pirep);
        $this->assertEquals($pirep->id, $dupe_pirep->id);

        /**
         * Create a PIREP outside of the check time interval
         */
        $minutes = setting('pireps.duplicate_check_time') + 1;
        $pirep = Pirep::factory()->create([
            'created_at' => Carbon::now('UTC')->subMinutes($minutes)->toDateTimeString(),
        ]);

        // This should find itself...
        $dupe_pirep = $this->pirepSvc->findDuplicate($pirep);
        $this->assertFalse($dupe_pirep);
    }

    public function testCancelViaAPI()
    {
        $pirep = $this->createPirep()->toArray();

        $uri = '/api/pireps/prefile';
        $response = $this->post($uri, $pirep);
        $pirep_id = $response->json()['data']['id'];

        $uri = '/api/pireps/'.$pirep_id.'/acars/position';
        $acars = Acars::factory()->make()->toArray();
        $response = $this->post($uri, [
            'positions' => [$acars],
        ]);

        $response->assertStatus(200);

        // Cancel it
        $uri = '/api/pireps/'.$pirep_id.'/cancel';
        $response = $this->delete($uri, $acars);
        $response->assertStatus(200);

        // Should get a 400 when posting an ACARS update
        $uri = '/api/pireps/'.$pirep_id.'/acars/position';
        $acars = Acars::factory()->make()->toArray();

        $response = $this->post($uri, $acars);
        $response->assertStatus(400);
    }

    /**
     * When a PIREP is accepted, ensure that the bid is removed
     */
    public function testPirepBidRemoved()
    {
        $bidSvc = app(BidService::class);
        $flightSvc = app(FlightService::class);

        $user = User::factory()->create([
            'flight_time' => 0,
        ]);

        $flight = Flight::factory()->create([
            'route_code' => null,
            'route_leg'  => null,
        ]);

        $bidSvc->addBid($flight, $user);

        $pirep = Pirep::factory()->create([
            'user_id'       => $user->id,
            'airline_id'    => $flight->airline_id,
            'flight_id'     => $flight->id,
            'flight_number' => $flight->flight_number,
        ]);

        $pirep = $this->pirepSvc->create($pirep, []);
        $this->pirepSvc->submit($pirep);

        $user_bid = Bid::where([
            'user_id'   => $user->id,
            'flight_id' => $flight->id,
        ])->first();

        $this->assertNull($user_bid);
    }

    public function testPirepProgressPercent()
    {
        $this->updateSetting('units.distance', 'km');

        /** @var User $user */
        $user = User::factory()->create();

        /** @var Pirep $pirep */
        $pirep = Pirep::factory()->create([
            'user_id'             => $user->id,
            'distance'            => 100,
            'planned_distance'    => 200,
            'flight_time'         => 60,
            'planned_flight_time' => 90,
        ]);

        $progress = $pirep->progress_percent;
        $this->assertEquals(50, $progress);

        $pirep->planned_distance = null;
        $progress = $pirep->progress_percent;
        $this->assertEquals(100, $progress);

        $pirep->planned_distance = 0;
        $progress = $pirep->progress_percent;
        $this->assertEquals(100, $progress);
    }

    /**
     * See that the notifications are properly formatted
     */
    public function testNotificationFormatting()
    {
        $this->updateSetting('units.distance', 'km');

        /** @var User $user */
        $user = User::factory()->create();

        /** @var Pirep $pirep */
        $pirep = Pirep::factory()->create([
            'user_id'             => $user->id,
            'distance'            => 100,
            'planned_distance'    => 200,
            'flight_time'         => 60,
            'planned_flight_time' => 90,
        ]);

        $discordNotif = new PirepPrefiled($pirep);
        $fields = $discordNotif->createFields($pirep);
        $this->assertEquals('1h 30m', $fields['Flight Time (Planned)']);
        $this->assertEquals('370.4 km', $fields['Distance']);

        $discordNotif = new PirepStatusChanged($pirep);
        $fields = $discordNotif->createFields($pirep);
        $this->assertEquals('1h 0m', $fields['Flight Time']);
        $this->assertEquals('185.2/370.4 km', $fields['Distance']);

        $discordNotif = new \App\Notifications\Messages\Broadcast\PirepFiled($pirep);
        $fields = $discordNotif->createFields($pirep);
        $this->assertEquals('1h 0m', $fields['Flight Time']);
        $this->assertEquals('185.2 km', $fields['Distance']);
    }

    public function testDiversionHandler()
    {
        $this->updateSetting('pireps.handle_diversion', true);
        $this->updateSetting('notifications.discord_pirep_diverted', true);

        /** @var User $user */
        $user = User::factory()->create();

        /** @var Airport $originalArrivalAirport */
        $originalArrivalAirport = Airport::factory()->create();

        /** @var Airport $diversionAirport */
        $diversionAirport = Airport::factory()->create();

        /** @var Aircraft $aircraft */
        $aircraft = Aircraft::factory()->create();

        /** @var Pirep $pirep */
        $pirep = Pirep::factory()->create([
            'user_id'        => $user->id,
            'aircraft_id'    => $aircraft->id,
            'arr_airport_id' => $originalArrivalAirport->id,
        ]);

        $this->pirepSvc->create($pirep, [
            [
                'name'   => 'Diversion Airport',
                'value'  => $diversionAirport->id,
                'source' => PirepFieldSource::ACARS,
            ],
        ]);

        $this->pirepSvc->submit($pirep);

        $pirep = Pirep::find($pirep->id);
        $this->assertEquals($diversionAirport->id, $pirep->arr_airport_id);
        $this->assertEquals($originalArrivalAirport->id, $pirep->alt_airport_id);
        $this->assertStringContainsString('DIVERTED FROM '.$originalArrivalAirport->id.' TO '.$diversionAirport->id, $pirep->notes);
        $this->assertNull($pirep->flight_id);
        $this->assertNull($pirep->route_leg);

        $user->refresh();
        $aircraft->refresh();

        $this->assertEquals($diversionAirport->id, $user->curr_airport_id);
        $this->assertEquals($diversionAirport->id, $aircraft->airport_id);

        Notification::assertSentTo([$pirep], PirepDiverted::class);
    }
}
