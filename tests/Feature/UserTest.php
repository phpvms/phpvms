<?php

use App\Events\ProfileUpdated;
use App\Exceptions\PilotIdNotFound;
use App\Exceptions\UserPilotIdExists;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Role;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;
use App\Services\UserService;
use App\Widgets\LatestPilots;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

test('rank subfleets', function () {
    $userSvc = app(UserService::class);

    // Add subfleets and aircraft, but also add another
    // set of subfleets
    $subfleet = Subfleet::factory()->hasAircraft(2)->count(2)->create();
    $subfleetA = $subfleet->first();

    $rank = Rank::factory()->hasAttached($subfleetA)->create();

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);

    $added_aircraft = $subfleetA->aircraft->pluck('id');

    $subfleets = $userSvc->getAllowableSubfleets($user);
    expect($subfleets->count())->toEqual(1);

    $subfleet = $subfleets[0];
    $all_aircraft = $subfleet->aircraft->pluck('id');
    expect($all_aircraft)->toEqual($added_aircraft);

    /**
     * Check via API
     */
    apiAs($user);

    $resp = $this->get('/api/user/fleet', [])->assertStatus(200);
    $body = $resp->json()['data'];

    // Get the subfleet that's been added in
    $subfleet_from_api = $body[0];
    expect($subfleet_from_api['id'])->toEqual($subfleet->id);

    // Get all the aircraft from that subfleet
    $aircraft_from_api = collect($subfleet_from_api['aircraft'])->pluck('id');
    expect($aircraft_from_api)->toEqual($added_aircraft);

    /**
     * Check the user ID call
     */
    $resp = $this->get('/api/users/'.$user->id.'/fleet', [])->assertStatus(200);
    $body = $resp->json()['data'];

    // Get the subfleet that's been added in
    $subfleet_from_api = $body[0];
    expect($subfleet_from_api['id'])->toEqual($subfleet->id);

    // Get all the aircraft from that subfleet
    $aircraft_from_api = collect($subfleet_from_api['aircraft'])->pluck('id');
    expect($aircraft_from_api)->toEqual($added_aircraft);
});

test('get all aircraft', function () {
    $fareSvc = app(FareService::class);
    $userSvc = app(UserService::class);

    // Add subfleets and aircraft, but also add another
    // set of subfleets
    $subfleetA = Subfleet::factory()->hasAircraft(2)->create();
    $subfleetB = Subfleet::factory()->hasAircraft(2)->create();

    $fare = Fare::factory()->create([
        'price'    => 20,
        'capacity' => 200,
    ]);

    $overrides = [
        'price'    => 50,
        'capacity' => 400,
    ];

    $fareSvc->setForSubfleet($subfleetA, $fare, $overrides);

    $added_aircraft = array_merge(
        $subfleetA->aircraft->pluck('id')->toArray(),
        $subfleetB->aircraft->pluck('id')->toArray()
    );

    $rank = Rank::factory()->hasAttached($subfleetA)->create();

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);

    apiAs($user);

    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $subfleets = $userSvc->getAllowableSubfleets($user);
    expect($subfleets->count())->toEqual(2);

    $all_aircraft = array_merge(
        $subfleets[0]->aircraft->pluck('id')->toArray(),
        $subfleets[1]->aircraft->pluck('id')->toArray()
    );

    expect($all_aircraft)->toEqual($added_aircraft);

    $subfleetACalled = collect($subfleets)->firstWhere('id', $subfleetA->id);
    expect($overrides['price'])->toEqual($subfleetACalled->fares[0]['price'])
        ->and($overrides['capacity'])->toEqual($subfleetACalled->fares[0]['capacity']);

    /**
     * Check via API, but should only show the single subfleet being returned
     */
    updateSetting('pireps.restrict_aircraft_to_rank', true);

    $resp = $this->get('/api/user/fleet', [], $user)->assertStatus(200);

    // Get all the aircraft from that subfleet, check the fares
    $body = $resp->json()['data'];
    $subfleetAFromApi = collect($body)->firstWhere('id', $subfleetA->id);
    expect($overrides['price'])->toEqual($subfleetAFromApi['fares'][0]['price'])
        ->and($overrides['capacity'])->toEqual($subfleetAFromApi['fares'][0]['capacity']);
});

test('get aircraft allowed from flight', function () {
    // Add subfleets and aircraft, but also add another
    // set of subfleets
    $airport = Airport::factory()->create();

    $subfleetA = Subfleet::factory()->hasAircraft(2, ['airport_id' => $airport->id])->create();
    $subfleetB = Subfleet::factory()->hasAircraft(2)->create();

    $rank = Rank::factory()->hasAttached($subfleetA)->create();
    $user = User::factory()->create([
        'curr_airport_id' => $airport->id,
        'rank_id'         => $rank->id,
    ]);

    apiAs($user);

    $flight = Flight::factory()->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => $airport->id,
    ]);

    $flight->subfleets()->syncWithoutDetaching([
        $subfleetA->id,
        $subfleetB->id,
    ]);

    // Make sure no flights are filtered out
    updateSetting('pilots.only_flights_from_current', false);

    // And restrict the aircraft
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $response = $this->get('/api/flights/'.$flight->id, [], $user);
    $response->assertStatus(200);
    expect($response->json()['data']['subfleets'])->toHaveCount(2);

    /*
     * Now make sure it's filtered out
     */
    updateSetting('pireps.restrict_aircraft_to_rank', true);

    /**
     * Make sure it's filtered out from the single flight call
     */
    $response = $this->get('/api/flights/'.$flight->id, [], $user);
    $response->assertStatus(200);
    expect($response->json()['data']['subfleets'])->toHaveCount(1);

    /**
     * Make sure it's filtered out from the flight list
     */
    $response = $this->get('/api/flights', [], $user);
    $body = $response->json()['data'];
    $response->assertStatus(200);
    expect($body[0]['subfleets'])->toHaveCount(1);

    /**
     * Filtered from search?
     */
    $response = $this->get('/api/flights/search?flight_id='.$flight->id, [], $user);
    $response->assertStatus(200);
    $body = $response->json()['data'];
    expect($body[0]['subfleets'])->toHaveCount(1);
});

test('api flight list excludes flights without allowable subfleets', function () {
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_flights_from_current', false);
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

    apiAs($user);

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

    $response = $this->get('/api/flights');
    $response->assertOk();

    $flightIds = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();

    expect($flightIds)
        ->toContain($allowedFlight->id)
        ->toContain($openFlight->id)
        ->not->toContain($restrictedFlight->id);

    $searchResponse = $this->get('/api/flights/search?flight_id='.$restrictedFlight->id);
    $searchResponse->assertOk();
    expect($searchResponse->json('data'))->toHaveCount(0);
});

test('user pilot id change already exists', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    app(UserService::class)->changePilotId($user1, $user2->pilot_id);
})->throws(UserPilotIdExists::class);

test('user pilot id split', function () {
    $userSvc = app(UserService::class);

    $user = User::factory()->create();
    $found_user = $userSvc->findUserByPilotId($user->ident);
    expect($found_user->id)->toEqual($user->id);

    // Look for them with the IATA code
    $found_user = $userSvc->findUserByPilotId($user->airline->iata.$user->id);
    expect($found_user->id)->toEqual($user->id);
});

test('user pilot id split invalid id', function () {
    /** @var User $user */
    $user = User::factory()->create();

    app(UserService::class)->findUserByPilotId($user->airline->iata);
})->throws(PilotIdNotFound::class);

test('user pilot id invalid iata', function () {
    $airline = Airline::factory()->create(['icao' => 'ABC', 'iata' => null]);
    $user = User::factory()->create(['airline_id' => $airline->id]);

    app(UserService::class)->findUserByPilotId('123');
})->throws(PilotIdNotFound::class);

test('user pilot id added', function () {
    $userSvc = app(UserService::class);

    $new_user = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $new_user['password'] = Hash::make('secret');
    $user = $userSvc->createUser($new_user);
    expect($user->pilot_id)->toEqual($user->id);

    // Add a second user
    $new_user = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $new_user['password'] = Hash::make('secret');
    $user2 = $userSvc->createUser($new_user);
    expect($user2->pilot_id)->toEqual($user2->id);

    // Now try to change the original user's pilot_id to 4
    $user = $userSvc->changePilotId($user, 4);
    expect($user->pilot_id)->toEqual(4);

    // Create a new user and the pilot_id should be 5
    $user3 = User::factory()->create();
    expect($user3->pilot_id)->toEqual(5);
});

test('user pilot deleted', function () {
    $userSvc = app(UserService::class);

    $new_user = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $new_user['password'] = Hash::make('secret');
    $admin_user = $userSvc->createUser($new_user);

    $new_user = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $new_user['password'] = Hash::make('secret');
    $user = $userSvc->createUser($new_user);
    expect($user->pilot_id)->toEqual($user->id);

    // Delete the user
    $userSvc->removeUser($user);

    $response = $this->get('/api/user/'.$user->id, [], $admin_user);
    $response->assertStatus(404);

    // Get from the DB
    $user = User::find($user->id);
    expect($user)->toBeNull();
});

test('user pilot deleted with pireps', function () {
    $userSvc = app(UserService::class);

    $new_user = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $new_user['password'] = Hash::make('secret');
    $admin_user = $userSvc->createUser($new_user);

    $new_user = User::factory()->make()->makeVisible(['api_key', 'name', 'email'])->toArray();
    $new_user['password'] = Hash::make('secret');
    $user = $userSvc->createUser($new_user);
    expect($user->pilot_id)->toEqual($user->id);

    /** @var Pirep $pirep */
    Pirep::factory()->create([
        'user_id' => $user->id,
    ]);

    // Delete the user
    $userSvc->removeUser($user);

    $response = $this->get('/api/user/'.$user->id, [], $admin_user);
    $response->assertStatus(404);

    // Get from the DB
    $user = User::find($user->id);
    expect($user->name)->toEqual('Deleted User');
    $this->assertNotEquals($new_user['password'], $user->password);
});

test('user name private', function () {
    $vals = [
        'Firstname'                     => 'Firstname',
        'Firstname Lastname'            => 'Firstname L',
        'Firstname Middlename Lastname' => 'Firstname Middlename L',
        'First Mid1 mid2 last'          => 'First Mid1 Mid2 L',
    ];

    foreach ($vals as $input => $expected) {
        $user = new User(['name' => $input]);
        expect($user->name_private)->toEqual($expected);
    }
});

test('user leave', function () {
    $userSvc = app(UserService::class);

    User::factory()->create([
        'status' => UserState::ACTIVE,
    ]);

    $users_on_leave = $userSvc->findUsersOnLeave();
    expect($users_on_leave)->toHaveCount(0);

    updateSetting('pilots.auto_leave_days', 1);
    $user = User::factory()->create([
        'state'      => UserState::ACTIVE,
        'status'     => UserState::ACTIVE,
        'created_at' => Carbon::now('UTC')->subDays(5),
    ]);

    $users_on_leave = $userSvc->findUsersOnLeave();
    expect($users_on_leave)->toHaveCount(1)
        ->and($users_on_leave->first()->id)->toEqual($user->id);

    // Give that user a new PIREP, still old
    $pirep = Pirep::factory()->create([
        'user_id'      => $user->id,
        'created_at'   => Carbon::now('UTC')->subDays(5),
        'submitted_at' => Carbon::now('UTC')->subDays(5),
    ]);

    $user->last_pirep_id = $pirep->id;
    $user->save();
    $user->refresh();

    $users_on_leave = $userSvc->findUsersOnLeave();
    expect($users_on_leave)->toHaveCount(1)
        ->and($users_on_leave->first()->id)->toEqual($user->id);

    // Create a new PIREP
    /** @var Pirep $pirep */
    $pirep = Pirep::factory()->create([
        'user_id'      => $user->id,
        'created_at'   => Carbon::now('UTC'),
        'submitted_at' => Carbon::now('UTC'),
    ]);

    $user->last_pirep_id = $pirep->id;
    $user->save();
    $user->refresh();

    $users_on_leave = $userSvc->findUsersOnLeave();
    expect($users_on_leave)->toHaveCount(0);

    // Check disable_activity_checks
    $user = User::factory()->create([
        'status'     => UserState::ACTIVE,
        'created_at' => Carbon::now('UTC')->subDays(5),
    ]);

    $role = Role::factory()->create([
        'disable_activity_checks' => true,
    ]);

    $user->assignRole($role);
    $user->save();

    $users_on_leave = $userSvc->findUsersOnLeave();
    expect($users_on_leave)->toHaveCount(0);
});

test('event called when profile updated', function () {
    Event::fake();
    $user = User::factory()->create();

    $body = [
        'name'       => 'Test User',
        'email'      => $user->email,
        'airline_id' => 1,
    ];

    $resp = $this->actingAs($user)->put('/profile/'.$user->id, $body);

    Event::assertDispatched(ProfileUpdated::class);
});

/*
|--------------------------------------------------------------------------
| Characterization tests for the /users page + LatestPilots widget
|--------------------------------------------------------------------------
| These tests lock in the CURRENT behavior of the public pilots list and
| the LatestPilots dashboard widget so that subsequent refactoring (Phase 4
| Prettus repository removal) can be verified to be behavior-preserving.
*/

test('pilots list renders all states when hide_inactive is off', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'PilotActive', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'PilotPending', 'state' => UserState::PENDING]);
    User::factory()->create(['name' => 'PilotLeave', 'state' => UserState::ON_LEAVE]);

    $response = $this->get('/users');

    $response->assertOk();
    $response->assertSee('PilotActive');
    $response->assertSee('PilotPending');
    $response->assertSee('PilotLeave');
});

test('pilots list filters to active when hide_inactive is on', function () {
    updateSetting('pilots.hide_inactive', true);

    User::factory()->create(['name' => 'PilotActive', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'PilotPending', 'state' => UserState::PENDING]);
    User::factory()->create(['name' => 'PilotRejected', 'state' => UserState::REJECTED]);

    $response = $this->get('/users');

    $response->assertOk();
    $response->assertSee('PilotActive');
    $response->assertDontSee('PilotPending');
    $response->assertDontSee('PilotRejected');
});

test('pilots list filters by free-text search', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'JohnDoe', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'JaneSmith', 'state' => UserState::ACTIVE]);

    $response = $this->get('/users?search=JohnDoe');

    $response->assertOk();
    $response->assertSee('JohnDoe');
    $response->assertDontSee('JaneSmith');
});

test('pilots list filters by field-specific search syntax', function () {
    updateSetting('pilots.hide_inactive', false);

    User::factory()->create(['name' => 'JohnDoe', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'BobSmith', 'state' => UserState::ACTIVE]);

    $response = $this->get('/users?search=name:JohnDoe');

    $response->assertOk();
    $response->assertSee('JohnDoe');
    $response->assertDontSee('BobSmith');
});

test('LatestPilots widget excludes deleted users and orders by created_at desc', function () {
    User::factory()->create([
        'name'       => 'Newest',
        'state'      => UserState::ACTIVE,
        'created_at' => Carbon::now('UTC'),
    ]);
    User::factory()->create([
        'name'       => 'Older',
        'state'      => UserState::ACTIVE,
        'created_at' => Carbon::now('UTC')->subDay(),
    ]);
    User::factory()->create([
        'name'       => 'Deleted',
        'state'      => UserState::DELETED,
        'created_at' => Carbon::now('UTC')->addHour(),
    ]);

    $widget = new LatestPilots(['count' => 5]);
    $rendered = (string) $widget->run()->render();

    expect($rendered)->toContain('Newest');
    expect($rendered)->toContain('Older');
    expect($rendered)->not->toContain('Deleted');

    $newestPos = strpos($rendered, 'Newest');
    $olderPos = strpos($rendered, 'Older');
    expect($newestPos)->toBeLessThan($olderPos);
});
