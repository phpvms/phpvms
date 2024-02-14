<?php

namespace Tests;

use App\Cron\Nightly\SetActiveFlights;
use App\Events\CronNightly;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\Days;
use App\Models\Enums\NavaidType;
use App\Models\Flight;
use App\Models\Navdata;
use App\Models\Subfleet;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\AirportService;
use App\Services\FleetService;
use App\Services\FlightService;
use Carbon\Carbon;
use Exception;

class FlightTest extends TestCase
{
    protected $flightSvc;
    protected $settingsRepo;

    public function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        $this->flightSvc = app(FlightService::class);
        $this->settingsRepo = app(SettingRepository::class);
    }

    /**
     * Test adding a flight and also if there are duplicates
     */
    public function testDuplicateFlight()
    {
        $this->user = User::factory()->create();
        $flight = $this->addFlight($this->user);

        // first flight shouldn't be a duplicate
        $this->assertFalse($this->flightSvc->isFlightDuplicate($flight));

        $flight_dupe = new Flight([
            'airline_id'     => $flight->airline_id,
            'flight_number'  => $flight->flight_number,
            'route_code'     => $flight->route_code,
            'route_leg'      => $flight->route_leg,
            'dpt_airport_id' => $flight->dpt_airport_id,
            'arr_airport_id' => $flight->arr_airport_id,
            'days'           => $flight->days,
        ]);

        $this->assertTrue($this->flightSvc->isFlightDuplicate($flight_dupe));

        // same flight but diff airline shouldn't be a dupe
        $new_airline = Airline::factory()->create();
        $flight_dupe = new Flight([
            'airline_id'     => $new_airline->airline_id,
            'flight_number'  => $flight->flight_number,
            'route_code'     => $flight->route_code,
            'route_leg'      => $flight->route_leg,
            'dpt_airport_id' => $flight->dpt_airport_id,
            'arr_airport_id' => $flight->arr_airport_id,
            'days'           => $flight->days,
        ]);

        $this->assertFalse($this->flightSvc->isFlightDuplicate($flight_dupe));

        // add another flight with a code
        $flight_leg = Flight::factory()->create([
            'airline_id'     => $flight->airline_id,
            'flight_number'  => $flight->flight_number,
            'route_code'     => 'A',
            'dpt_airport_id' => $flight->dpt_airport_id,
            'arr_airport_id' => $flight->arr_airport_id,
            'days'           => $flight->days,
        ]);

        $this->assertFalse($this->flightSvc->isFlightDuplicate($flight_leg));

        // Add both a route and leg
        $flight_leg = Flight::factory()->create([
            'airline_id'     => $flight->airline_id,
            'flight_number'  => $flight->flight_number,
            'route_code'     => 'A',
            'route_leg'      => 1,
            'dpt_airport_id' => $flight->dpt_airport_id,
            'arr_airport_id' => $flight->arr_airport_id,
            'days'           => $flight->days,
        ]);

        $this->assertFalse($this->flightSvc->isFlightDuplicate($flight_leg));
    }

    public function testGetFlight()
    {
        $this->user = User::factory()->create();
        $flight = $this->addFlight($this->user, [
            'load_factor'          => '',
            'load_factor_variance' => '',
        ]);

        $req = $this->get('/api/flights/'.$flight->id);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertEquals($flight->id, $body['id']);
        $this->assertEquals($flight->dpt_airport_id, $body['dpt_airport_id']);
        $this->assertEquals($flight->arr_airport_id, $body['arr_airport_id']);
        $this->assertEquals(setting('flights.default_load_factor'), $body['load_factor']);

        // Distance conversion
        $this->assertHasKeys($body['distance'], ['mi', 'nmi', 'km']);

        $this->get('/api/flights/INVALID', self::$auth_headers)
            ->assertStatus(404);
    }

    /**
     * Search based on all different criteria
     */
    public function testSearchFlight()
    {
        /** @var \App\Models\User user */
        $this->user = User::factory()->create();
        $flight = $this->addFlight($this->user);

        /** @var \App\Services\FlightService $flightSvc */
        $flightSvc = app(FlightService::class);
        $flightSvc->updateCustomFields($flight, [
            ['name' => '0', 'value' => 'value'],
        ]);

        // search specifically for a flight ID
        $query = 'flight_id='.$flight->id;
        $req = $this->get('/api/flights/search?'.$query);
        $req->assertStatus(200);

        $data = $req->json('data');
        $this->assertEquals(1, count($data));
    }

    public function testSearchFlightInactiveAirline()
    {
        /** @var \App\Models\Airline $airline_inactive */
        $airline_inactive = Airline::factory()->create(['active' => 0]);

        /** @var \App\Models\Airline $airline_active */
        $airline_active = Airline::factory()->create(['active' => 1]);
        $this->user = User::factory()->create([
            'airline_id' => $airline_inactive->id,
        ]);

        $this->addFlight($this->user, ['airline_id' => $airline_inactive->id]);
        $this->addFlight($this->user, ['airline_id' => $airline_active->id]);

        // search specifically for a flight ID
        $req = $this->get('/api/flights/search?ignore_restrictions=1');
        $req->assertStatus(200);
        $body = $req->json('data');

        $this->assertEquals(1, count($body));
        $this->assertEquals($airline_active->id, $body[0]['airline_id']);
    }

    /**
     * Get the flight's route
     *
     * @throws Exception
     */
    public function testFlightRoute()
    {
        $this->user = User::factory()->create();
        $flight = $this->addFlight($this->user);

        $route_count = random_int(4, 6);
        $route = Navdata::factory()->count($route_count)->create();
        $route_text = implode(' ', $route->pluck('id')->toArray());

        $flight->route = $route_text;
        $flight->save();

        $req = $this->get('/api/flights/'.$flight->id);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertEquals($flight->load_factor, $body['load_factor']);

        $res = $this->get('/api/flights/'.$flight->id.'/route');
        $res->assertStatus(200);
        $body = $res->json();

        $this->assertCount($route_count, $body['data']);

        $first_point = $body['data'][0];
        $this->assertEquals($first_point['id'], $route[0]->id);
        $this->assertEquals($first_point['name'], $route[0]->name);
        $this->assertEquals($first_point['type']['type'], $route[0]->type);
        $this->assertEquals($first_point['type']['name'], NavaidType::label($route[0]->type));
    }

    /**
     * Find all of the flights
     */
    public function testFindAllFlights()
    {
        $this->user = User::factory()->create();
        Flight::factory()->count(20)->create([
            'airline_id' => $this->user->airline_id,
        ]);

        $res = $this->get('/api/flights?limit=10');

        $body = $res->json();
        $this->assertEquals(2, $body['meta']['last_page']);

        $res = $this->get('/api/flights?page=2&limit=5');
        $res->assertJsonCount(5, 'data');
    }

    /**
     * Search for flights based on a subfleet. If subfleet is blank
     */
    public function testSearchFlightBySubfleet()
    {
        $airline = Airline::factory()->create();
        $subfleetA = Subfleet::factory()->create(['airline_id' => $airline->id]);
        $subfleetB = Subfleet::factory()->create(['airline_id' => $airline->id]);

        $rank = $this->createRank(0, [$subfleetB->id]);
        $this->user = User::factory()->create([
            'airline_id' => $airline->id,
            'rank_id'    => $rank->id,
        ]);

        $this->addFlightsForSubfleet($subfleetA, 5);
        $this->addFlightsForSubfleet($subfleetB, 10);

        // search specifically for a given subfleet
        //$query = 'subfleet_id='.$subfleetB->id;
        $query = 'subfleet_id='.$subfleetB->id;
        $res = $this->get('/api/flights/search?'.$query);
        $res->assertStatus(200);
        $res->assertJsonCount(10, 'data');

        $meta = $res->json('meta');

        $body = $res->json('data');
        collect($body)->each(function ($flight) use ($subfleetB) {
            self::assertNotEmpty($flight['subfleets']);
            self::assertEquals($subfleetB->id, $flight['subfleets'][0]['id']);
        });
    }

    /**
     * Search for flights based on a subfleet. If subfleet is blank
     */
    public function testSearchFlightBySubfleetPagination()
    {
        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Subfleet $subfleetA */
        $subfleetA = Subfleet::factory()->create(['airline_id' => $airline->id]);

        /** @var Subfleet $subfleetB */
        $subfleetB = Subfleet::factory()->create(['airline_id' => $airline->id]);

        $rank = $this->createRank(0, [$subfleetB->id]);
        $this->user = User::factory()->create([
            'airline_id' => $airline->id,
            'rank_id'    => $rank->id,
        ]);

        $this->addFlightsForSubfleet($subfleetA, 5);
        $this->addFlightsForSubfleet($subfleetB, 10);

        // search specifically for a given subfleet
        //$query = 'subfleet_id='.$subfleetB->id;
        $query = 'subfleet_id='.$subfleetB->id.'&limit=2';
        $res = $this->get('/api/flights/search?'.$query);
        $res->assertStatus(200);
        $res->assertJsonCount(2, 'data');

        $meta = $res->json('meta');
        $this->assertNull($meta['prev_page']);
        $this->assertNotNull($meta['next_page']);
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(10, $meta['total']);

        $body = $res->json('data');
        collect($body)->each(function ($flight) use ($subfleetB) {
            self::assertNotEmpty($flight['subfleets']);
            self::assertEquals($subfleetB->id, $flight['subfleets'][0]['id']);
        });
    }

    /**
     * Test the bitmasks that they work for setting the day of week and
     * then retrieving by searching on those
     */
    public function testFindDaysOfWeek(): void
    {
        /** @var User user */
        $this->user = User::factory()->create();

        Flight::factory()->count(20)->create([
            'airline_id' => $this->user->airline_id,
        ]);

        /** @var Flight $saved_flight */
        $saved_flight = Flight::factory()->create([
            'airline_id' => $this->user->airline_id,
            'days'       => Days::getDaysMask([
                Days::SUNDAY,
                Days::THURSDAY,
            ]),
        ]);

        /** @var Flight $flight */
        $flight = Flight::findByDays([Days::SUNDAY])->first();
        $this->assertTrue($flight->on_day(Days::SUNDAY));
        $this->assertTrue($flight->on_day(Days::THURSDAY));
        $this->assertFalse($flight->on_day(Days::MONDAY));
        $this->assertEquals($saved_flight->id, $flight->id);

        $flight = Flight::findByDays([Days::SUNDAY, Days::THURSDAY])->first();
        $this->assertEquals($saved_flight->id, $flight->id);

        $flight = Flight::findByDays([Days::WEDNESDAY, Days::THURSDAY])->first();
        $this->assertNull($flight);
    }

    /**
     * Make sure that flights are marked as inactive when they're out of the start/end
     * zones. also make sure that flights with a specific day of the week are only
     * active on those days
     */
    public function testDayOfWeekActive(): void
    {
        /** @var User user */
        $this->user = User::factory()->create();

        // Set it to Monday or Tuesday, depending on what today is
        if (date('N') === '1') { // today is a monday
            $days = Days::getDaysMask([Days::TUESDAY]);
        } else {
            $days = Days::getDaysMask([Days::MONDAY]);
        }

        Flight::factory()->count(5)->create();

        /** @var Flight $flight */
        $flight = Flight::factory()->create([
            'days' => $days,
        ]);

        // Run the event that will enable/disable flights
        $event = new CronNightly();
        (new SetActiveFlights())->handle($event);

        $res = $this->get('/api/flights');
        $body = $res->json('data');

        $flights = collect($body)->where('id', $flight->id)->first();
        $this->assertNull($flights);
    }

    public function testDayOfWeekTests(): void
    {
        $mask = 127;
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[1]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[2]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[3]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[4]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[5]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[6]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[7]));

        $mask = 125;
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[1]));
        $this->assertFalse(Days::in($mask, Days::$isoDayMap[2]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[3]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[4]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[5]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[6]));
        $this->assertTrue(Days::in($mask, Days::$isoDayMap[7]));

        $mask = [];
        $this->assertFalse(Days::in($mask, Days::$isoDayMap[1]));

        $mask = 0;
        $this->assertFalse(Days::in($mask, Days::$isoDayMap[1]));
    }

    public function testStartEndDate(): void
    {
        $this->user = User::factory()->create();

        Flight::factory()->count(5)->create();
        $flight = Flight::factory()->create([
            'start_date' => Carbon::now('UTC')->subDays(1),
            'end_date'   => Carbon::now('UTC')->addDays(1),
        ]);

        $flight_not_active = Flight::factory()->create([
            'start_date' => Carbon::now('UTC')->subDays(10),
            'end_date'   => Carbon::now('UTC')->subDays(2),
        ]);

        // Run the event that will enable/disable flights
        $event = new CronNightly();
        (new SetActiveFlights())->handle($event);

        $res = $this->get('/api/flights');
        $body = $res->json('data');

        $flights = collect($body)->where('id', $flight->id)->first();
        $this->assertNotNull($flights);

        $flights = collect($body)->where('id', $flight_not_active->id)->first();
        $this->assertNull($flights);
    }

    public function testStartEndDateDayOfWeek(): void
    {
        $this->user = User::factory()->create();

        // Set it to Monday or Tuesday, depending on what today is
        if (date('N') === '1') { // today is a monday
            $days = Days::getDaysMask([Days::TUESDAY]);
        } else {
            $days = Days::getDaysMask([Days::MONDAY]);
        }

        Flight::factory()->count(5)->create();
        $flight = Flight::factory()->create([
            'start_date' => Carbon::now('UTC')->subDays(1),
            'end_date'   => Carbon::now('UTC')->addDays(1),
            'days'       => Days::$isoDayMap[date('N')],
        ]);

        // Not active because of days of week not today
        $flight_not_active = Flight::factory()->create([
            'start_date' => Carbon::now('UTC')->subDays(1),
            'end_date'   => Carbon::now('UTC')->addDays(1),
            'days'       => $days,
        ]);

        // Run the event that will enable/disable flights
        $event = new CronNightly();
        (new SetActiveFlights())->handle($event);

        $res = $this->get('/api/flights');
        $body = $res->json('data');

        $flights = collect($body)->where('id', $flight->id)->first();
        $this->assertNotNull($flights);

        $flights = collect($body)->where('id', $flight_not_active->id)->first();
        $this->assertNull($flights);
    }

    public function testFlightSearchApi()
    {
        $this->user = User::factory()->create();
        $flights = Flight::factory()->count(10)->create([
            'airline_id' => $this->user->airline_id,
        ]);

        $flight = $flights->random();

        $query = 'flight_number='.$flight->flight_number;
        $req = $this->get('/api/flights/search?'.$query);
        $body = $req->json();

        $this->assertEquals($flight->id, $body['data'][0]['id']);
    }

    public function testFlightSearchApiDepartureAirport()
    {
        $this->user = User::factory()->create();
        Flight::factory()->count(10)->create([
            'airline_id' => $this->user->airline_id,
        ]);

        $flight = Flight::factory()->create([
            'airline_id'     => $this->user->airline_id,
            'dpt_airport_id' => 'KAUS',
        ]);

        $query = 'dpt_airport_id=kaus';
        $req = $this->get('/api/flights/search?'.$query);
        $body = $req->json();

        $this->assertCount(1, $body['data']);
        $this->assertEquals($flight->id, $body['data'][0]['id']);
    }

    public function testFlightSearchApiDistance()
    {
        $total_flights = 10;

        /** @var \App\Models\User user */
        $this->user = User::factory()->create();

        /** @var \App\Models\Flight $flights */
        $flights = Flight::factory()->count($total_flights)->create([
            'airline_id' => $this->user->airline_id,
        ]);

        // Max distance generated in factory is 1000, so set a random flight
        // and try to find it again through the search

        $flight = $flights->random();
        $flight->distance = 1500;
        $flight->save();

        $distance_gt = 1100;
        $distance_lt = 1600;

        // look for all of the flights now less than the "factory default" of 1000
        $query = 'dlt=1000&ignore_restrictions=1';
        $req = $this->get('/api/flights/search?'.$query);
        $body = $req->json();
        $this->assertCount($total_flights - 1, $body['data']);

        // Try using greater than
        $query = 'dgt='.$distance_gt.'&ignore_restrictions=1';
        $req = $this->get('/api/flights/search?'.$query);
        $body = $req->json();

        $this->assertCount(1, $body['data']);
        $this->assertEquals($flight->id, $body['data'][0]['id']);

        $query = 'dgt='.$distance_gt.'&dlt='.$distance_lt.'&ignore_restrictions=1';
        $req = $this->get('/api/flights/search?'.$query);
        $body = $req->json();
        $this->assertCount(1, $body['data']);
        $this->assertEquals($flight->id, $body['data'][0]['id']);
    }

    public function testAddSubfleet()
    {
        $subfleet = Subfleet::factory()->create();
        $flight = Flight::factory()->create();

        $fleetSvc = app(FleetService::class);
        $fleetSvc->addSubfleetToFlight($subfleet, $flight);

        $flight->refresh();
        $found = $flight->subfleets()->get();
        $this->assertCount(1, $found);

        // Make sure it hasn't been added twice
        $fleetSvc->addSubfleetToFlight($subfleet, $flight);
        $flight->refresh();
        $found = $flight->subfleets()->get();
        $this->assertCount(1, $found);
    }

    /**
     * Delete a flight and make sure all the bids are gone
     */
    public function testDeleteFlight()
    {
        $user = User::factory()->create();

        $flight = $this->addFlight($user);
        $this->flightSvc->deleteFlight($flight);

        $empty_flight = Flight::find($flight->id);
        $this->assertNull($empty_flight);
    }

    public function testAirportDistance()
    {
        // KJFK
        $fromIcao = Airport::factory()->create([
            'lat' => 40.6399257,
            'lon' => -73.7786950,
        ]);

        // KSFO
        $toIcao = Airport::factory()->create([
            'lat' => 37.6188056,
            'lon' => -122.3754167,
        ]);

        $airportSvc = app(AirportService::class);
        $distance = $airportSvc->calculateDistance($fromIcao->id, $toIcao->id);
        $this->assertNotNull($distance);
        $this->assertEquals(2244.33, $distance['nmi']);
    }

    public function testAirportDistanceApi()
    {
        $user = User::factory()->create();
        $headers = $this->headers($user);

        // KJFK
        $fromIcao = Airport::factory()->create([
            'lat' => 40.6399257,
            'lon' => -73.7786950,
        ]);

        // KSFO
        $toIcao = Airport::factory()->create([
            'lat' => 37.6188056,
            'lon' => -122.3754167,
        ]);

        $req = $this->get('/api/airports/'.$fromIcao->id.'/distance/'.$toIcao->id, $headers);
        $req->assertStatus(200);

        $body = $req->json()['data'];

        $this->assertNotNull($body['distance']);
        $this->assertEquals(2244.33, $body['distance']['nmi']);
    }
}
