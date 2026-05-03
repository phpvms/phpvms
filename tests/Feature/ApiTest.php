<?php

// use Swagger\Serializer;
use App\Http\Resources\AirlineResource;
use App\Http\Resources\AirportResource;
use App\Http\Resources\NewsResource;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Models\Fare;
use App\Models\News;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;
use App\Support\Utils;

test('api authentication', function () {
    $user = User::factory()->create();

    $uri = '/api/user';

    // Missing auth header
    $res = $this->get($uri);
    $res->assertStatus(401);

    // Test invalid API key
    $this->withHeaders(['Authorization' => 'invalidKey'])->get($uri)
        ->assertStatus(401);

    $this->withHeaders(['Authorization' => ''])->get($uri)
        ->assertStatus(401);

    // Test upper/lower case of Authorization header, etc
    $response = $this->withHeader('Authorization', $user->api_key)->get($uri);
    $body = $response->json();
    $response->assertStatus(200)->assertJson(['data' => ['id' => $user->id]]);

    $this->withHeaders(['x-api-key' => $user->api_key])->get($uri)
        ->assertJson(['data' => ['id' => $user->id]]);

    $this->withHeaders(['x-API-key' => $user->api_key])->get($uri)
        ->assertJson(['data' => ['id' => $user->id]]);

    $this->withHeaders(['X-API-KEY' => $user->api_key])->get($uri)
        ->assertJson(['data' => ['id' => $user->id]]);
});

it('should deny a non active user', function () {
    $uri = '/api/user';

    $user = User::factory()->create([
        'state' => UserState::PENDING,
    ]);
    $this->withHeader('Authorization', $user->api_key)->get($uri)->assertStatus(401);

    $user = User::factory()->create([
        'state' => UserState::REJECTED,
    ]);
    $this->withHeader('Authorization', $user->api_key)->get($uri)->assertStatus(401);

    $user = User::factory()->create([
        'state' => UserState::DELETED,
    ]);
    $this->withHeader('Authorization', $user->api_key)->get($uri)->assertStatus(401);

    $user = User::factory()->create([
        'state' => UserState::SUSPENDED,
    ]);
    $this->withHeader('Authorization', $user->api_key)->get($uri)->assertStatus(401);
});

it('can retrieve news', function () {
    $news = News::factory()->create();
    $response = $this->get('/api/news');

    $expected = NewsResource::collection([$news])->resolve();

    $response->assertSuccessful();

    expect($response)->assertJsonCount(1, 'data')
        ->and($response->json('data'))->toEqual($expected);
});

it('can retrieve airlines', function () {
    // Clear out base data
    Airline::truncate();

    $size = random_int(5, 10);
    $user = User::factory()->create([
        'airline_id' => 0,
    ]);

    apiAs($user);

    $airlines = Airline::factory()->count($size)->create();

    $res = $this->get('/api/airlines');

    $res->assertSuccessful()
        ->assertJsonCount($size, 'data');

    $expected = AirlineResource::collection($airlines)->resolve();
    expect($res->json('data'))->toEqualCanonicalizing($expected);

    $airline = $airlines->random();
    $response = $this->get('/api/airlines/'.$airline->id);

    $response->assertSuccessful();

    $expected = AirlineResource::make($airline)->resolve();
    expect($response->json('data'))->toEqual($expected);

});

test('get airlines chinese chars', function () {
    // Clear out base data
    Airline::truncate();

    $user = User::factory()->create([
        'airline_id' => 0,
    ]);
    apiAs($user);

    $airlines = Airline::factory()->count(4)->sequence(
        [
            'icao' => 'DKH',
            'name' => '吉祥航空',
        ],
        [
            'icao' => 'CSZ',
            'name' => '深圳航空',
        ],
        [
            'icao' => 'CCA',
            'name' => '中国国际航空',
        ],
        [
            'icao' => 'CXA',
            'name' => '厦门航空',
        ]
    )->create();

    $res = $this->get('/api/airlines');
    $res->assertSuccessful()
        ->assertJsonCount(4, 'data');

    $expected = AirlineResource::collection($airlines)->resolve();
    expect($res->json('data'))->toEqualCanonicalizing($expected);
});

it('should paginate results', function () {
    $size = random_int(5, 10);
    $user = User::factory()->create([
        'airline_id' => 0,
    ]);
    apiAs($user);

    Subfleet::factory()->count($size)->create();

    /*
     * Page 0 and page 1 should return the same thing
     */
    // Test pagination
    $res = $this->get('/api/fleet?limit=1&page=0');
    $res->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $body = $res->json('data');
    $id_first = $body[0]['id'];

    $res = $this->get('/api/fleet?limit=1&page=1');
    $res->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $body = $res->json('data');

    $id_second = $body[0]['id'];

    expect($id_second)->toEqual($id_first);

    /*
     * Page 2 should be different from page 1
     */
    $res = $this->get('/api/fleet?limit=1&page=2');
    $res->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $body = $res->json('data');

    $id_third = $body[0]['id'];

    $this->assertNotEquals($id_first, $id_third);
});

it('preserves ?limit= in /api/fleet pagination metadata', function () {
    $user = User::factory()->create(['airline_id' => 0]);
    apiAs($user);

    Subfleet::factory()->count(5)->create();

    $res = $this->get('/api/fleet?limit=2');
    $res->assertSuccessful();

    // The codebase wraps paginators with CustomPaginatedResourceResponse,
    // which moves the next-page URL onto meta.next_page. Both the page
    // size AND the next-page URL must carry ?limit= forward so clients
    // can follow pagination without losing page size.
    expect($res->json('meta.per_page'))->toEqual(2);

    $next = $res->json('meta.next_page');
    expect($next)->not->toBeNull()
        ->and($next)->toContain('limit=2');
});

it('preserves ?limit= in /api/news pagination metadata', function () {
    $user = User::factory()->create(['airline_id' => 0]);
    apiAs($user);

    News::factory()->count(5)->create();

    $res = $this->get('/api/news?limit=2');
    $res->assertSuccessful();

    expect($res->json('meta.per_page'))->toEqual(2);

    $next = $res->json('meta.next_page');
    expect($next)->not->toBeNull()
        ->and($next)->toContain('limit=2');
});

it('returns a real paginator on /api/user/fleet', function () {
    $user = User::factory()->create(['airline_id' => 0]);
    apiAs($user);

    // Without rank restriction the endpoint exposes all subfleets,
    // which is the simplest setup that exercises pagination here.
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    Subfleet::factory()->count(5)->create();

    $res = $this->get('/api/user/fleet?limit=2');
    $res->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'next_page'],
        ]);

    // The migration prior to this test caused getAllowableSubfleets() to
    // unwrap the paginator via transform(); now it preserves it via
    // through(), so meta.next_page must be a real URL with ?limit= intact.
    expect($res->json('meta.per_page'))->toEqual(2);

    $next = $res->json('meta.next_page');
    expect($next)->not->toBeNull()
        ->and($next)->toContain('limit=2');
});

it('can retrieve an airport', function () {
    $user = User::factory()->create();
    apiAs($user);

    $airport = Airport::factory()->create();

    $response = $this->get('/api/airports/'.$airport->icao);

    $response->assertSuccessful();

    $expected = AirportResource::make($airport)->resolve();

    expect($response->json('data'))->toMatchArray(Arr::except($expected, ['deleted_at']));

    $this->get('/api/airports/UNK')->assertStatus(404);
});

test('airport request5 char', function () {
    $user = User::factory()->create();
    apiAs($user);

    /** @var Airport $airport */
    $airport = Airport::factory()->create(['icao' => '5Char']);

    $response = $this->get('/api/airports/'.$airport->icao);

    $response->assertStatus(200);
    $response->assertJson(['data' => ['icao' => $airport->icao]]);

    $this->get('/api/airports/UNK')->assertStatus(404);
});

test('get all airports', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(70)->create();

    $response = $this->get('/api/airports/')
        ->assertStatus(200);

    $body = $response->json();
    expect($body)->toHaveKeys(['data', 'links', 'meta']);
});

test('get all airports hubs', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(10)->create();
    Airport::factory()->create(['hub' => 1]);

    $this->get('/api/airports/hubs')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('get subfleets', function () {
    $user = User::factory()->create();
    apiAs($user);

    $subfleetA = Subfleet::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $subfleetB = Subfleet::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    $subfleetA_size = random_int(2, 10);
    $subfleetB_size = random_int(2, 10);
    Aircraft::factory()->count($subfleetA_size)->create([
        'subfleet_id' => $subfleetA->id,
    ]);

    Aircraft::factory()->count($subfleetB_size)->create([
        'subfleet_id' => $subfleetB->id,
    ]);

    $response = $this->get('/api/fleet');
    $response->assertStatus(200);
    $body = $response->json()['data'];

    foreach ($body as $subfleet) {
        $size = $subfleet['id'] === $subfleetA->id ? $subfleetA_size : $subfleetB_size;

        expect($subfleet['aircraft'])->toHaveCount($size);
        foreach ($subfleet['aircraft'] as $aircraft) {
            expect($aircraft['ident'])->not->toBeEmpty();
        }
    }
});

test('get aircraft', function () {
    $user = User::factory()->create();
    apiAs($user);

    $fare_svc = app(FareService::class);

    /** @var Subfleet $subfleet */
    $subfleet = Subfleet::factory()->create([
        'airline_id' => $user->airline_id,
    ]);

    /** @var Fare $fare */
    $fare = Fare::factory()->create();

    $fare_svc->setForSubfleet($subfleet, $fare);

    /** @var Aircraft $aircraft */
    $aircraft = Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'mtow'        => 93000.0,
        'zfw'         => 71500.0,
    ]);

    /**
     * Just try retrieving by ID
     */
    $resp = $this->get('/api/fleet/aircraft/'.$aircraft->id);
    $body = $resp->json()['data'];

    expect($aircraft->id)->toEqual($body['id']);
    expect($aircraft->name)->toEqual($body['name']);
    expect($body['ident'])->not->toBeEmpty();

    // $this->assertEquals($body['mtow'], $aircraft->mtow->local(0));
    // $this->assertEquals($body['zfw'], $aircraft->zfw->local(0));
    $resp = $this->get('/api/fleet/aircraft/'.$aircraft->id.'?registration='.$aircraft->registration);
    $body = $resp->json()['data'];

    expect($aircraft->id)->toEqual($body['id']);
    expect($aircraft->name)->toEqual($body['name']);

    // $this->assertEquals($body['mtow'], $aircraft->mtow->local(0));
    // $this->assertEquals($body['zfw'], $aircraft->zfw->local(0));
    expect($body['ident'])->not->toBeEmpty();
    expect($aircraft->ident)->toEqual($body['ident']);

    $resp = $this->get('/api/fleet/aircraft/'.$aircraft->id.'?icao='.$aircraft->icao);
    $body = $resp->json()['data'];

    expect($aircraft->id)->toEqual($body['id']);
    expect($aircraft->name)->toEqual($body['name']);

    // $this->assertEquals($body['mtow'], $aircraft->mtow->local(0));
    // $this->assertEquals($body['zfw'], $aircraft->zfw->local(0));
});

test('get user', function () {
    $user = User::factory()->create([
        'avatar' => '/assets/avatar.jpg',
    ]);

    apiAs($user);

    $res = $this->get('/api/user')->assertStatus(200);
    $user = $res->json('data');
    expect($user)->not->toBeNull();
    $this->assertNotSame(-1, strpos($user['avatar'], 'http'));

    // Should go to gravatar
    $user = User::factory()->create();

    $res = $this->get('/api/user')->assertStatus(200);
    $user = $res->json('data');
    expect($user)->not->toBeNull();
    $this->assertNotSame(-1, strpos($user['avatar'], 'gravatar'));
});

test('web cron', function () {
    updateSetting('cron.random_id', '');

    $this->get('/api/cron/sdf')->assertStatus(400);

    $id = Utils::generateNewId(24);
    updateSetting('cron.random_id', $id);

    $this->get('/api/cron/sdf')->assertStatus(400);

    $res = $this->get('/api/cron/'.$id);
    $res->assertStatus(200);
});

test('status', function () {
    $res = $this->get('/api/status');
    $status = $res->json();

    expect($status['version'])->not->toBeEmpty();
    expect($status['php'])->not->toBeEmpty();
});
