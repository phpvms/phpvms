<?php

use App\Cron\Nightly\SetActiveFlights;
use App\Events\CronNightly;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\Days;
use App\Models\Enums\NavaidType;
use App\Models\Flight;
use App\Models\Navdata;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\AirportService;
use App\Services\FleetService;
use App\Services\FlightService;
use Carbon\Carbon;

test('duplicate flight', function () {
    $user = User::factory()->create();

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $flightSvc = app(FlightService::class);

    // first flight shouldn't be a duplicate
    expect($flightSvc->isFlightDuplicate($flight))->toBeFalse();

    $flight_dupe = new Flight([
        'airline_id'     => $flight->airline_id,
        'flight_number'  => $flight->flight_number,
        'route_code'     => $flight->route_code,
        'route_leg'      => $flight->route_leg,
        'dpt_airport_id' => $flight->dpt_airport_id,
        'arr_airport_id' => $flight->arr_airport_id,
        'days'           => $flight->days,
    ]);

    expect($flightSvc->isFlightDuplicate($flight_dupe))->toBeTrue();

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

    expect($flightSvc->isFlightDuplicate($flight_dupe))->toBeFalse();

    // add another flight with a code
    $flight_leg = Flight::factory()->create([
        'airline_id'     => $flight->airline_id,
        'flight_number'  => $flight->flight_number,
        'route_code'     => 'A',
        'dpt_airport_id' => $flight->dpt_airport_id,
        'arr_airport_id' => $flight->arr_airport_id,
        'days'           => $flight->days,
    ]);

    expect($flightSvc->isFlightDuplicate($flight_leg))->toBeFalse();

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

    expect($flightSvc->isFlightDuplicate($flight_leg))->toBeFalse();
});

test('get flight', function () {
    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create([
        'airline_id'           => $user->airline_id,
        'load_factor'          => '',
        'load_factor_variance' => '',
    ]);

    $req = $this->get('/api/flights/'.$flight->id);
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body['id'])->toEqual($flight->id)
        ->and($body['dpt_airport_id'])->toEqual($flight->dpt_airport_id)
        ->and($body['arr_airport_id'])->toEqual($flight->arr_airport_id)
        ->and($body['load_factor'])->toEqual(setting('flights.default_load_factor'))
        ->and($body['distance'])->toHaveKeys(['mi', 'nmi', 'km']);

    $this->get('/api/flights/INVALID')
        ->assertStatus(404);
});

test('search flight', function () {
    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $flightSvc = app(FlightService::class);
    $flightSvc->updateCustomFields($flight, [
        ['name' => '0', 'value' => 'value'],
    ]);

    // search specifically for a flight ID
    $query = 'flight_id='.$flight->id;
    $req = $this->get('/api/flights/search?'.$query);
    $req->assertStatus(200);

    $data = $req->json('data');
    expect($data)->toHaveCount(1);
});

test('search flight inactive airline', function () {
    $airline_inactive = Airline::factory()->create(['active' => 0]);

    $airline_active = Airline::factory()->create(['active' => 1]);
    $user = User::factory()->create([
        'airline_id' => $airline_inactive->id,
    ]);

    apiAs($user);

    Flight::factory()->create([
        'airline_id' => $airline_inactive->id,
    ]);

    Flight::factory()->create([
        'airline_id' => $airline_active->id,
    ]);

    // search specifically for a flight ID
    $req = $this->get('/api/flights/search?ignore_restrictions=1');
    $req->assertStatus(200);
    $body = $req->json('data');

    expect($body)->toHaveCount(1)
        ->and($body[0]['airline_id'])->toEqual($airline_active->id);
});

test('flight route', function () {
    $user = User::factory()->create();
    apiAs($user);

    $route_count = random_int(4, 6);
    $route = Navdata::factory()->count($route_count)->create();
    $route_text = implode(' ', $route->pluck('id')->toArray());

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
        'route'      => $route_text,
    ]);

    $req = $this->get('/api/flights/'.$flight->id);
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body['load_factor'])->toEqual($flight->load_factor);

    $res = $this->get('/api/flights/'.$flight->id.'/route');
    $res->assertStatus(200);
    $body = $res->json();

    expect($body['data'])->toHaveCount($route_count);

    $first_point = $body['data'][0];
    expect($route[0]->id)->toEqual($first_point['id'])
        ->and($route[0]->name)->toEqual($first_point['name'])
        ->and($route[0]->type)->toEqual($first_point['type']['type'])
        ->and(NavaidType::label($route[0]->type))->toEqual($first_point['type']['name']);
});

test('find all flights', function () {
    $user = User::factory()->create();
    apiAs($user);

    Flight::factory()->count(20)->create([
        'airline_id' => $user->airline_id,
    ]);

    $res = $this->get('/api/flights?limit=10');

    $body = $res->json();
    expect($body['meta']['last_page'])->toEqual(2);

    $res = $this->get('/api/flights?page=2&limit=5');
    $res->assertJsonCount(5, 'data');
});

test('frontend flight list hides restricted flights and keeps open flights', function () {
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_show_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $airline = Airline::factory()->create();
    $allowedSubfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $restrictedSubfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $rank = Rank::factory()->hasAttached($allowedSubfleet)->create();

    /** @var User $user */
    $user = User::factory()->create([
        'airline_id' => $airline->id,
        'rank_id'    => $rank->id,
    ]);

    $allowedFlight = Flight::factory()->create([
        'airline_id' => $airline->id,
    ]);
    $allowedFlight->subfleets()->syncWithoutDetaching([$allowedSubfleet->id]);

    $restrictedFlight = Flight::factory()->create([
        'airline_id' => $airline->id,
    ]);
    $restrictedFlight->subfleets()->syncWithoutDetaching([$restrictedSubfleet->id]);

    $openFlight = Flight::factory()->create([
        'airline_id' => $airline->id,
    ]);

    $response = $this->actingAs($user)->get('/flights');
    $response->assertOk();

    $flights = collect($response->viewData('flights')->items());
    $flightIds = $flights->pluck('id')->map(fn ($id) => (int) $id)->all();

    expect($flightIds)
        ->toContain($allowedFlight->id)
        ->toContain($openFlight->id)
        ->not->toContain($restrictedFlight->id);
});

test('frontend flight list prefers explicit ordering over sortable aliases', function () {
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_show_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);

    $airline = Airline::factory()->create();

    /** @var User $user */
    $user = User::factory()->create([
        'airline_id' => $airline->id,
    ]);

    Flight::factory()->create([
        'airline_id'     => $airline->id,
        'flight_number'  => 200,
        'dpt_time'       => '23:00:00',
        'route_code'     => 'A',
        'route_leg'      => 1,
        'dpt_airport_id' => Airport::factory()->create()->id,
        'arr_airport_id' => Airport::factory()->create()->id,
    ]);
    Flight::factory()->create([
        'airline_id'     => $airline->id,
        'flight_number'  => 100,
        'dpt_time'       => '01:00:00',
        'route_code'     => 'A',
        'route_leg'      => 1,
        'dpt_airport_id' => Airport::factory()->create()->id,
        'arr_airport_id' => Airport::factory()->create()->id,
    ]);

    $response = $this->actingAs($user)->get('/flights?sort=dpt_time&direction=desc&orderBy=flight_number&sortedBy=asc');
    $response->assertOk();

    $flightNumbers = collect($response->viewData('flights')->items())
        ->pluck('flight_number')
        ->map(fn ($flightNumber) => (int) $flightNumber)
        ->all();

    expect($flightNumbers)->toBe([100, 200]);
});

test('search flight by subfleet', function () {
    $airline = Airline::factory()->create();
    $subfleetA = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $subfleetB = Subfleet::factory()->create(['airline_id' => $airline->id]);

    $rank = Rank::factory()->hasAttached($subfleetB)->create();
    $user = User::factory()->create([
        'airline_id' => $airline->id,
        'rank_id'    => $rank->id,
    ]);
    apiAs($user);

    Flight::factory()->hasAttached($subfleetA)->count(5)->create([
        'airline_id' => $subfleetA->airline_id,
    ]);

    Flight::factory()->hasAttached($subfleetB)->count(10)->create([
        'airline_id' => $subfleetB->airline_id,
    ]);

    // search specifically for a given subfleet
    // $query = 'subfleet_id='.$subfleetB->id;
    $query = 'subfleet_id='.$subfleetB->id;
    $res = $this->get('/api/flights/search?'.$query);
    $res->assertStatus(200);
    $res->assertJsonCount(10, 'data');

    $meta = $res->json('meta');

    $body = $res->json('data');
    collect($body)->each(function ($flight) use ($subfleetB) {
        expect($flight['subfleets'])->not->toBeEmpty()
            ->and($subfleetB->id)->toEqual($flight['subfleets'][0]['id']);
    });
});

test('search flight by subfleet pagination', function () {
    $airline = Airline::factory()->create();

    $subfleetA = Subfleet::factory()->create(['airline_id' => $airline->id]);

    $subfleetB = Subfleet::factory()->create(['airline_id' => $airline->id]);

    $rank = Rank::factory()->hasAttached($subfleetB)->create();

    $user = User::factory()->create([
        'airline_id' => $airline->id,
        'rank_id'    => $rank->id,
    ]);
    apiAs($user);

    Flight::factory()->hasAttached($subfleetA)->count(5)->create([
        'airline_id' => $subfleetA->airline_id,
    ]);

    Flight::factory()->hasAttached($subfleetB)->count(10)->create([
        'airline_id' => $subfleetB->airline_id,
    ]);

    // search specifically for a given subfleet
    // $query = 'subfleet_id='.$subfleetB->id;
    $query = 'subfleet_id='.$subfleetB->id.'&limit=2';
    $res = $this->get('/api/flights/search?'.$query);
    $res->assertStatus(200);
    $res->assertJsonCount(2, 'data');

    $meta = $res->json('meta');
    expect($meta['prev_page'])->toBeNull()
        ->and($meta['next_page'])->not->toBeNull()
        ->and($meta['current_page'])->toEqual(1)
        ->and($meta['total'])->toEqual(10);

    $body = $res->json('data');
    collect($body)->each(function ($flight) use ($subfleetB) {
        expect($flight['subfleets'])->not->toBeEmpty()
            ->and($subfleetB->id)->toEqual($flight['subfleets'][0]['id']);
    });
});

test('find days of week', function () {
    $user = User::factory()->create();

    Flight::factory()->count(20)->create([
        'airline_id' => $user->airline_id,
    ]);

    /** @var Flight $saved_flight */
    $saved_flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
        'days'       => Days::getDaysMask([
            Days::SUNDAY,
            Days::THURSDAY,
        ]),
    ]);

    /** @var Flight $flight */
    $flight = Flight::findByDays([Days::SUNDAY])->first();
    expect($flight->on_day(Days::SUNDAY))->toBeTrue()
        ->and($flight->on_day(Days::THURSDAY))->toBeTrue()
        ->and($flight->on_day(Days::MONDAY))->toBeFalse()
        ->and($flight->id)->toEqual($saved_flight->id);

    $flight = Flight::findByDays([Days::SUNDAY, Days::THURSDAY])->first();
    expect($flight->id)->toEqual($saved_flight->id);

    $flight = Flight::findByDays([Days::WEDNESDAY, Days::THURSDAY])->first();
    expect($flight)->toBeNull();
});

test('day of week active', function () {
    $user = User::factory()->create();

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

    $flights = collect($body)->firstWhere('id', $flight->id);
    expect($flights)->toBeNull();
});

test('day of week tests', function () {
    $mask = 127;
    expect(Days::in($mask, Days::$isoDayMap[1]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[2]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[3]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[4]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[5]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[6]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[7]))->toBeTrue();

    $mask = 125;
    expect(Days::in($mask, Days::$isoDayMap[1]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[2]))->toBeFalse()
        ->and(Days::in($mask, Days::$isoDayMap[3]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[4]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[5]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[6]))->toBeTrue()
        ->and(Days::in($mask, Days::$isoDayMap[7]))->toBeTrue();

    $mask = [];
    expect(Days::in($mask, Days::$isoDayMap[1]))->toBeFalse();

    $mask = 0;
    expect(Days::in($mask, Days::$isoDayMap[1]))->toBeFalse();
});

test('start end date', function () {
    $user = User::factory()->create();
    apiAs($user);

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

    $flights = collect($body)->firstWhere('id', $flight->id);
    expect($flights)->not->toBeNull();

    $flights = collect($body)->firstWhere('id', $flight_not_active->id);
    expect($flights)->toBeNull();
});

test('start end date day of week', function () {
    $user = User::factory()->create();
    apiAs($user);

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

    $flights = collect($body)->firstWhere('id', $flight->id);
    expect($flights)->not->toBeNull();

    $flights = collect($body)->firstWhere('id', $flight_not_active->id);
    expect($flights)->toBeNull();
});

test('flight search api', function () {
    $user = User::factory()->create();
    apiAs($user);

    $flights = Flight::factory()->count(10)->create([
        'airline_id' => $user->airline_id,
    ]);

    $flight = $flights->random();

    $query = 'flight_number='.$flight->flight_number;
    $req = $this->get('/api/flights/search?'.$query);
    $body = $req->json();

    expect($body['data'][0]['id'])->toEqual($flight->id);
});

test('flight search api departure airport', function () {
    $user = User::factory()->create();
    apiAs($user);

    Flight::factory()->count(10)->create([
        'airline_id' => $user->airline_id,
    ]);

    $flight = Flight::factory()->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => 'KAUS',
    ]);

    $query = 'dpt_airport_id=kaus';
    $req = $this->get('/api/flights/search?'.$query);
    $body = $req->json();

    expect($body['data'])->toHaveCount(1);
    expect($body['data'][0]['id'])->toEqual($flight->id);
});

test('flight search api distance', function () {
    $total_flights = 10;

    $user = User::factory()->create();
    apiAs($user);

    $flights = Flight::factory()->count($total_flights)->create([
        'airline_id' => $user->airline_id,
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
    expect($body['data'])->toHaveCount($total_flights - 1);

    // Try using greater than
    $query = 'dgt='.$distance_gt.'&ignore_restrictions=1';
    $req = $this->get('/api/flights/search?'.$query);
    $body = $req->json();

    expect($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['id'])->toEqual($flight->id);

    $query = 'dgt='.$distance_gt.'&dlt='.$distance_lt.'&ignore_restrictions=1';
    $req = $this->get('/api/flights/search?'.$query);
    $body = $req->json();
    expect($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['id'])->toEqual($flight->id);
});

test('add subfleet', function () {
    $subfleet = Subfleet::factory()->create();
    $flight = Flight::factory()->create();

    $fleetSvc = app(FleetService::class);
    $fleetSvc->addSubfleetToFlight($subfleet, $flight);

    $flight->refresh();
    $found = $flight->subfleets()->get();
    expect($found)->toHaveCount(1);

    // Make sure it hasn't been added twice
    $fleetSvc->addSubfleetToFlight($subfleet, $flight);
    $flight->refresh();
    $found = $flight->subfleets()->get();
    expect($found)->toHaveCount(1);
});

test('delete flight', function () {
    $user = User::factory()->create();
    apiAs($user);

    $flightSvc = app(FlightService::class);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $flightSvc->deleteFlight($flight);

    $empty_flight = Flight::find($flight->id);
    expect($empty_flight)->toBeNull();
});

test('airport distance', function () {
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
    expect($distance)->not->toBeNull()
        ->and($distance['nmi'])->toEqual(2244.33);
});

test('airport distance api', function () {
    $user = User::factory()->create();
    apiAs($user);

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

    $req = $this->get('/api/airports/'.$fromIcao->id.'/distance/'.$toIcao->id);
    $req->assertStatus(200);

    $body = $req->json()['data'];

    expect($body['distance'])->not->toBeNull()
        ->and($body['distance']['nmi'])->toEqual(2244.33);
});
