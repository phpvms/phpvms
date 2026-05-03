<?php

use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Enums\UserState;
use App\Models\Flight;
use App\Models\Navdata;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Notifications\Messages\Broadcast\PirepDiverted;
use App\Notifications\Messages\Broadcast\PirepPrefiled;
use App\Notifications\Messages\Broadcast\PirepStatusChanged;
use App\Notifications\Messages\PirepAccepted;
use App\Notifications\Messages\PirepFiled;
use App\Services\AircraftService;
use App\Services\BidService;
use App\Services\PirepService;
use App\Support\Units\Fuel;
use Carbon\Carbon;
use Database\Seeders\ShieldSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Nwidart\Modules\Facades\Module;

use function Pest\Laravel\seed;

beforeEach(function () {
    loadYamlIntoDb('fleet');
});

function createNewRoute(): array
{
    $route = [];
    $navpoints = Navdata::factory()->count(5)->create();
    foreach ($navpoints as $point) {
        $route[] = $point->id;
    }

    return $route;
}

function getAcarsRoute(Pirep $pirep): array
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

test('add pirep', function () {
    $user = User::factory()->create();
    apiAs($user);

    Notification::fake();

    $pirepSvc = app(PirepService::class);

    $route = createNewRoute();
    $pirep = Pirep::factory()->create([
        'user_id' => $user->id,
        'route'   => implode(' ', $route),
    ]);

    $pirep = $pirepSvc->create($pirep, []);

    try {
        $pirepSvc->saveRoute($pirep);
    } catch (Exception $e) {
        throw $e;
    }

    /*
     * Check the initial state info
     */
    expect($pirep->state)->toEqual(PirepState::PENDING);

    /**
     * Now set the PIREP state to ACCEPTED
     */
    $new_pirep_count = $pirep->user->flights + 1;
    $new_flight_time = $pirep->user->flight_time + $pirep->flight_time;

    $pirepSvc->changeState($pirep, PirepState::ACCEPTED);
    expect($pirep->pilot->flights)->toEqual($new_pirep_count)
        ->and($pirep->pilot->flight_time)->toEqual($new_flight_time)
        ->and($pirep->pilot->curr_airport_id)->toEqual($pirep->arr_airport_id)
        ->and($pirep->arr_airport_id)->toEqual($pirep->aircraft->airport_id);

    // Check the location of the current aircraft

    // Also check via API:
    $this->get('/api/fleet/aircraft/'.$pirep->aircraft_id, [])
        ->assertJson(['data' => ['airport_id' => $pirep->arr_airport_id]]);

    // Make sure a notification was sent out to both the user and the admin(s)
    Notification::assertSentTo([$user], PirepAccepted::class);

    // Try cancelling it
    $uri = '/api/pireps/'.$pirep->id.'/cancel';
    $response = $this->put($uri);
    $response->assertStatus(400);

    // Try updating some data on it
    $uri = '/api/pireps/'.$pirep->id.'/update';
    $response = $this->put($uri, [
        'state' => 'FIL',
    ]);

    $response->assertStatus(400);

    /**
     * Now go from ACCEPTED to REJECTED
     */
    $new_pirep_count = $pirep->pilot->flights - 1;
    $new_flight_time = $pirep->pilot->flight_time - $pirep->flight_time;
    $pirepSvc->changeState($pirep, PirepState::REJECTED);
    expect($pirep->pilot->flights)->toEqual($new_pirep_count)
        ->and($pirep->pilot->flight_time)->toEqual($new_flight_time)
        ->and($pirep->pilot->curr_airport_id)->toEqual($pirep->arr_airport_id);

    /**
     * Check the ACARS table
     */
    $saved_route = getAcarsRoute($pirep);
    expect($saved_route)->toEqual($route);

    /**
     * Recreate the route with new options points. Make sure that the
     * old route is erased from the ACARS table and then recreated
     */
    $route = createNewRoute();
    $pirep->route = implode(' ', $route);
    $pirep->save();

    // this should delete the old route from the acars table
    $pirepSvc->saveRoute($pirep);

    $saved_route = getAcarsRoute($pirep);
    expect($saved_route)->toEqual($route);
});

test('unit fields', function () {
    $pirep = createPirep();
    $pirep->save();

    $uri = '/api/pireps/'.$pirep->id;

    $response = $this->get($uri);
    $body = $response->json('data');

    expect($body['block_fuel'])->toHaveKeys(['lbs', 'kg'])
        ->and(round($body['block_fuel']['lbs']))->toEqual(round($pirep->block_fuel->toUnit('lbs')))
        ->and($body['fuel_used'])->toHaveKeys(['lbs', 'kg'])
        ->and(round($body['fuel_used']['lbs']))->toEqual(round($pirep->fuel_used->toUnit('lbs')))
        ->and($body['distance'])->toHaveKeys(['km', 'nmi', 'mi'])
        ->and(round($body['distance']['nmi']))->toEqual(round($pirep->distance->toUnit('nmi')))
        ->and($body['planned_distance'])->toHaveKeys(['km', 'nmi', 'mi'])
        ->and(round($body['planned_distance']['nmi']))->toEqual(round($pirep->planned_distance->toUnit('nmi')));

    // Check conversion on save
    $val = random_int(1000, 9999999);
    $pirep->block_fuel = $val;
    $pirep->fuel_used = $val;

    // no conversion with plain numbers
    expect($val)->toEqual($pirep->block_fuel->internal(2))
        ->and($val)->toEqual($pirep->fuel_used->internal(2));

    // no conversion with lbs
    $pirep->block_fuel = new Fuel($val, 'lbs');
    expect(round($val, 2))->toEqual($pirep->block_fuel->internal(2));

    $pirep->fuel_used = new Fuel($val, 'lbs');
    expect(round($val, 2))->toEqual($pirep->fuel_used->internal(2));

    // conversion of kg to lbs
    $pirep->block_fuel = new Fuel($val, 'kg');
    expect(Fuel::make($val, 'kg')->toUnit('lbs', 2))->toEqual($pirep->block_fuel->internal(2));

    $pirep->fuel_used = new Fuel($val, 'kg');
    expect(Fuel::make($val, 'kg')->toUnit('lbs', 2))->toEqual($pirep->fuel_used->internal(2));
});

test('get user pireps', function () {
    $user = User::factory()->create();
    apiAs($user);

    $pirep_done = Pirep::factory()->create([
        'user_id' => $user->id,
        'state'   => PirepState::ACCEPTED,
    ]);

    $pirep_in_progress = Pirep::factory()->create([
        'user_id' => $user->id,
        'state'   => PirepState::IN_PROGRESS,
    ]);

    $pirep_cancelled = Pirep::factory()->create([
        'user_id' => $user->id,
        'state'   => PirepState::CANCELLED,
    ]);

    $pireps = $this->get('/api/user/pireps')
        ->assertStatus(200)
        ->json();

    $pirep_ids = collect($pireps['data'])->pluck('id');

    expect($pirep_ids->contains($pirep_done->id))->toBeTrue()
        ->and($pirep_ids->contains($pirep_in_progress->id))->toBeTrue()
        ->and($pirep_ids->contains($pirep_cancelled->id))->toBeFalse();

    // Get only status
    $pireps = $this->get('/api/user/pireps?state='.PirepState::IN_PROGRESS)
        ->assertStatus(200)
        ->json();

    $pirep_ids = collect($pireps['data'])->pluck('id');
    expect($pirep_ids->contains($pirep_in_progress->id))->toBeTrue()
        ->and($pirep_ids->contains($pirep_done->id))->toBeFalse()
        ->and($pirep_ids->contains($pirep_cancelled->id))->toBeFalse();
});

test('pirep notifications', function () {
    seed(ShieldSeeder::class);

    $pirepSvc = app(PirepService::class);

    Notification::fake();

    $user = User::factory()->create([
        'name'        => 'testPirepNotifications user',
        'flights'     => 0,
        'flight_time' => 0,
        'rank_id'     => 1,
    ]);

    $admin = createAdminUser(['name' => 'testPirepNotifications Admin']);

    $pirep = Pirep::factory()->create([
        'airline_id' => 1,
        'user_id'    => $user->id,
    ]);

    $pirepSvc->create($pirep);
    $pirepSvc->submit($pirep);

    // Make sure a notification was sent out to the admin
    Notification::assertSentTo([$admin], PirepFiled::class);
    Notification::assertNotSentTo([$user], PirepFiled::class);
});

test('pilot stats incr', function () {
    $pirepSvc = app(PirepService::class);
    updateSetting('pilots.count_transfer_hours', false);

    // Let's create two ranks
    $rank = Rank::factory()->create([
        'name'         => 'New Pilot',
        'hours'        => 0,
        'auto_promote' => true,
    ]);

    $rank2 = Rank::factory()->create([
        'name'                => 'Junior First Officer',
        'hours'               => 10,
        'auto_promote'        => true,
        'auto_approve_acars'  => true,
        'auto_approve_manual' => true,
    ]);

    $aircraft = Aircraft::factory()->create();

    $user = User::factory()->create([
        'flights'     => 0,
        'flight_time' => 0,
        'rank_id'     => $rank->id,
    ]);

    // Submit two PIREPs
    $pireps = Pirep::factory()->count(2)->create([
        'airline_id'  => $user->airline_id,
        'aircraft_id' => $aircraft->id,
        'user_id'     => $user->id,
        // 360min == 6 hours, rank should bump up
        'flight_time' => 360,
    ]);

    $flight_time_initial = $aircraft->flight_time;

    foreach ($pireps as $pirep) {
        $pirepSvc->create($pirep);
        $pirepSvc->accept($pirep);
    }

    $pilot = User::find($user->id);
    $last_pirep = Pirep::where('id', $pilot->last_pirep_id)->first();

    // Make sure rank went up
    expect($pilot->rank_id)->toBeGreaterThan($user->rank_id)
        ->and($pilot->curr_airport_id)->toEqual($last_pirep->arr_airport_id)
        ->and($pilot->flights)->toEqual(2);

    $aircraft->refresh();
    $after_time = $flight_time_initial + 720;
    expect($aircraft->flight_time)->toEqual($after_time);

    //
    // Submit another PIREP, adding another 6 hours
    // it should automatically be accepted
    //
    $pirep = Pirep::factory()->create([
        'airline_id' => $user->airline_id,
        'user_id'    => $user->id,
        // 120min == 2 hours, currently at 9 hours
        // Rank bumps up at 10 hours
        'flight_time' => 120,
    ]);

    // Pilot should be at rank 2, where accept should be automatic
    $pirepSvc->create($pirep);
    $pirepSvc->submit($pirep);

    $pilot->refresh();

    expect($pilot->flights)->toEqual(3);

    $latest_pirep = Pirep::where('id', $pilot->last_pirep_id)->first();

    // Make sure PIREP was auto updated
    expect($latest_pirep->state)->toEqual(PirepState::ACCEPTED);

    // Make sure latest PIREP was updated
    $this->assertNotEquals($last_pirep->id, $latest_pirep->id);
});

test('pilot dont change rank', function () {
    $pirepSvc = app(PirepService::class);

    $rank = Rank::factory()->create([
        'hours'        => 15,
        'auto_promote' => false,
    ]);

    // Set the user to the above rank, non-promote, they shouldn't bump down
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

    $pirepSvc->create($pirep);
    $pirepSvc->accept($pirep);

    $pilot = User::find($user->id);

    // Make sure rank didn't change
    expect($pilot->rank_id)->toEqual($rank->id);
});

test('pilot stats incr with transfer hours', function () {
    $pirepSvc = app(PirepService::class);
    updateSetting('pilots.count_transfer_hours', true);

    // Let's create two ranks
    $rank = Rank::factory()->create([
        'name'         => 'New Pilot',
        'hours'        => 0,
        'auto_promote' => true,
    ]);

    $rank2 = Rank::factory()->create([
        'name'               => 'Junior First Officer',
        'hours'              => 10,
        'auto_promote'       => true,
        'auto_approve_acars' => true,
    ]);

    $user = User::factory()->create([
        'flights'       => 0,
        'flight_time'   => 0,
        'transfer_time' => 720,
        'rank_id'       => $rank->id,
    ]);

    $aircraft = Aircraft::factory()->create();

    // Submit two PIREPs
    // 1 hour flight times, but the rank should bump up because of the transfer hours
    $pireps = Pirep::factory()->count(2)->create([
        'airline_id'  => $user->airline_id,
        'aircraft_id' => $aircraft->id,
        'user_id'     => $user->id,
        'flight_time' => 60,
    ]);

    foreach ($pireps as $pirep) {
        $pirepSvc->create($pirep);
        $pirepSvc->accept($pirep);
    }

    $pilot = User::find($user->id);
    $last_pirep = $pilot->last_pirep;
    expect($last_pirep->id)->toEqual($pilot->last_pirep_id);

    // Make sure rank went up
    expect($pilot->rank_id)->toBeGreaterThan($user->rank_id);

    // Check the aircraft
    $aircraft->refresh();
    expect($aircraft->flight_time)->toEqual(120);

    // Reset the aircraft flight time
    $aircraft->update([
        'flight_time' => 10,
    ]);

    // Recalculate the status
    /** @var AircraftService $aircraftSvc */
    $aircraftSvc = app(AircraftService::class);
    $aircraftSvc->recalculateStats();

    $aircraft->refresh();
    expect($aircraft->flight_time)->toEqual(120);
});

test('pilot status change', function () {
    $pirepSvc = app(PirepService::class);
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

    $pirepSvc->create($pirep);
    $pirepSvc->submit($pirep);

    /** @var User $user */
    $user = User::find($user->id);
    expect($user->state)->toEqual(UserState::ACTIVE);
});

test('duplicate pireps', function () {
    $pirepSvc = app(PirepService::class);
    $user = User::factory()->create();
    $pirep = Pirep::factory()->create([
        'user_id' => $user->id,
    ]);

    // This should find itself...
    $dupe_pirep = $pirepSvc->findDuplicate($pirep);
    $this->assertNotFalse($dupe_pirep);
    expect($dupe_pirep->id)->toEqual($pirep->id);

    /**
     * Create a PIREP outside of the check time interval
     */
    $minutes = setting('pireps.duplicate_check_time') + 1;
    $pirep = Pirep::factory()->create([
        'created_at' => Carbon::now('UTC')->subMinutes($minutes)->toDateTimeString(),
    ]);

    // This should find itself...
    $dupe_pirep = $pirepSvc->findDuplicate($pirep);
    expect($dupe_pirep)->toBeFalse();
});

test('cancel via api', function () {
    $pirep = createPirep();

    apiAs($pirep->user);

    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';
    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
    $pirep_id = $response->json()['data']['id'];

    $uri = '/api/pireps/'.$pirep_id.'/acars/position';
    $acars = Acars::factory()->make()->toArray();
    $acars = transformData($acars);

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
});

test('pirep bid removed', function () {
    $bidSvc = app(BidService::class);
    $pirepSvc = app(PirepService::class);

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

    $pirep = $pirepSvc->create($pirep, []);
    $pirepSvc->submit($pirep);

    $user_bid = Bid::where([
        'user_id'   => $user->id,
        'flight_id' => $flight->id,
    ])->first();

    expect($user_bid)->toBeNull();
});

test('pirep create returns not found for missing flight', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/pireps/create?flight_id=INVALID')
        ->assertNotFound();
});

test('pirep progress percent', function () {
    updateSetting('units.distance', 'km');

    $user = User::factory()->create();

    $pirep = Pirep::factory()->create([
        'user_id'             => $user->id,
        'distance'            => 100,
        'planned_distance'    => 200,
        'flight_time'         => 60,
        'planned_flight_time' => 90,
    ]);

    $progress = $pirep->progress_percent;
    expect($progress)->toEqual(50);

    $pirep->planned_distance = null;
    $progress = $pirep->progress_percent;
    expect($progress)->toEqual(100);

    $pirep->planned_distance = 0;
    $progress = $pirep->progress_percent;
    expect($progress)->toEqual(100);
});

test('notification formatting', function () {
    updateSetting('units.distance', 'km');

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
    expect($fields['Flight Time (Planned)'])->toEqual('1h 30m')
        ->and($fields['Distance'])->toEqual('370.4 km');

    $discordNotif = new PirepStatusChanged($pirep);
    $fields = $discordNotif->createFields($pirep);
    expect($fields['Flight Time'])->toEqual('1h 0m')
        ->and($fields['Distance'])->toEqual('185.2/370.4 km');

    $discordNotif = new App\Notifications\Messages\Broadcast\PirepFiled($pirep);
    $fields = $discordNotif->createFields($pirep);
    expect($fields['Flight Time'])->toEqual('1h 0m')
        ->and($fields['Distance'])->toEqual('185.2 km');
});

test('diversion handler', function () {
    updateSetting('pireps.handle_diversion', true);
    updateSetting('notifications.discord_pirep_diverted', true);

    Notification::fake();

    $pirepSvc = app(PirepService::class);

    $user = User::factory()->create();

    $originalArrivalAirport = Airport::factory()->create();

    $diversionAirport = Airport::factory()->create();

    $aircraft = Aircraft::factory()->create();

    $pirep = Pirep::factory()->create([
        'user_id'        => $user->id,
        'aircraft_id'    => $aircraft->id,
        'arr_airport_id' => $originalArrivalAirport->id,
    ]);

    $pirepSvc->create($pirep, [
        [
            'name'   => 'Diversion Airport',
            'value'  => $diversionAirport->id,
            'source' => PirepFieldSource::ACARS,
        ],
    ]);

    $pirepSvc->submit($pirep);

    $pirep = Pirep::find($pirep->id);
    expect($pirep->arr_airport_id)->toEqual($diversionAirport->id)
        ->and($pirep->alt_airport_id)->toEqual($originalArrivalAirport->id)
        ->and($pirep->notes)->toContain('DIVERTED FROM '.$originalArrivalAirport->id.' TO '.$diversionAirport->id)
        ->and($pirep->flight_id)->toBeNull()
        ->and($pirep->route_leg)->toBeNull();

    $user->refresh();
    $aircraft->refresh();

    expect($user->curr_airport_id)->toEqual($diversionAirport->id)
        ->and($aircraft->airport_id)->toEqual($diversionAirport->id);

    Notification::assertSentTo([$pirep], PirepDiverted::class);
});

test('diversion handler reuses matching reposition flight and attaches subfleet', function () {
    updateSetting('pireps.handle_diversion', true);

    Schema::create('vmsacars_config', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('value')->nullable();
    });

    DB::table('vmsacars_config')->insert([
        'id'    => 'disable_free_flights',
        'value' => '1',
    ]);

    Module::shouldReceive('find')->andReturn(Mockery::mock(Nwidart\Modules\Module::class));

    $pirepSvc = app(PirepService::class);

    $airline = Airline::factory()->create();
    $departureAirport = Airport::factory()->create();
    $originalArrivalAirport = Airport::factory()->create();
    $diversionAirport = Airport::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $aircraft = Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'airport_id'  => $departureAirport->id,
    ]);
    $user = User::factory()->create([
        'airline_id'      => $airline->id,
        'curr_airport_id' => $departureAirport->id,
    ]);
    $flight = Flight::factory()->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $departureAirport->id,
        'arr_airport_id' => $originalArrivalAirport->id,
        'callsign'       => 'TEST6000',
        'flight_number'  => 6000,
    ]);
    $flight->subfleets()->syncWithoutDetaching([$subfleet->id]);

    $repositionFlight = Flight::factory()->create([
        'airline_id'     => $airline->id,
        'flight_number'  => $flight->flight_number,
        'callsign'       => $flight->callsign,
        'route_code'     => PirepStatus::DIVERTED,
        'dpt_airport_id' => $diversionAirport->id,
        'arr_airport_id' => $originalArrivalAirport->id,
        'user_id'        => $user->id,
    ]);

    $pirep = Pirep::factory()->create([
        'airline_id'     => $airline->id,
        'user_id'        => $user->id,
        'aircraft_id'    => $aircraft->id,
        'flight_id'      => $flight->id,
        'flight_number'  => $flight->flight_number,
        'dpt_airport_id' => $departureAirport->id,
        'arr_airport_id' => $originalArrivalAirport->id,
    ]);

    $pirepSvc->create($pirep, [
        [
            'name'   => 'Diversion Airport',
            'value'  => $diversionAirport->id,
            'source' => PirepFieldSource::ACARS,
        ],
    ]);

    $pirepSvc->submit($pirep);

    $matchingFlights = Flight::query()->where([
        'airline_id'     => $airline->id,
        'flight_number'  => $flight->flight_number,
        'callsign'       => $flight->callsign,
        'route_code'     => PirepStatus::DIVERTED,
        'dpt_airport_id' => $diversionAirport->id,
        'arr_airport_id' => $originalArrivalAirport->id,
        'user_id'        => $user->id,
    ])->get();

    expect($matchingFlights)->toHaveCount(1)
        ->and($repositionFlight->fresh()->subfleets->pluck('id')->all())->toBe([$subfleet->id]);
});
