<?php

use App\Exceptions\AircraftNotAtAirport;
use App\Exceptions\UserNotAtAirport;
use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Fare;
use App\Models\Navdata;
use App\Models\PirepFare;
use App\Models\PirepFieldValue;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;
use App\Support\Utils;

function allPointsInRoute(array $route, array $points, array $addtl_fields = []): void
{
    if ($addtl_fields === []) {
        $addtl_fields = [];
    }

    $fields = array_merge(
        [
            'name',
            'order',
            'lat',
            'lon',
        ],
        $addtl_fields
    );

    expect($points)->toHaveCount(count($route));
    foreach ($route as $idx => $point) {
        expect($points[$idx])->toHaveKeys($fields);
        foreach ($fields as $f) {
            if ($f === 'lat' || $f === 'lon') {
                continue;
            }

            expect($points[$idx][$f])->toEqual($point[$f]);
        }
    }
}

function getPirepFromApi(string $pirep_id): array
{
    $resp = test()->get('/api/pireps/'.$pirep_id);
    $resp->assertStatus(200);

    return $resp->json()['data'];
}

it('should return a prefile error', function () {
    $user = User::factory()->create();
    apiAs($user);

    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();
    $aircraft = Aircraft::factory()->create();

    /*
     * Invalid airline id field
     */
    $uri = '/api/pireps/prefile';
    $pirep = [
        '_airline_id'         => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(400);
});

it('should not start pirep if the aircraft is not at the departure airport', function () {
    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', true);

    $user = User::factory()->create();
    apiAs($user);

    $airport = Airport::factory()->create();

    $aircraft_airport = Airport::factory()->create();

    $airline = Airline::factory()->create();

    $aircraft = Aircraft::factory()->create(['airport_id' => $aircraft_airport->id]);

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'Tests',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(400);
    expect($response->json('title'))->toEqual('The aircraft is not at the departure airport')
        ->and($response->json('error.message'))->toEqual(AircraftNotAtAirport::MESSAGE);
});

it('should not start pirep without airports', function () {
    $user = User::factory()->create();
    apiAs($user);

    $airline = Airline::factory()->create();
    $aircraft = Aircraft::factory()->create();

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => null,
        'arr_airport_id'      => null,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_flight_time' => 120,
        'source_name'         => 'ACARSTESTS',
        'route'               => 'POINTA POINTB',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(400);

    expect($response->json('details'))->toEqual('A departure airport is required, An arrival airport is required');
});

it('should not start pirep if the pilot is not at the departure airport', function () {
    updateSetting('pilots.only_flights_from_current', true);
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $user = User::factory()->create([
        'curr_airport_id' => 'KJFK',
    ]);
    apiAs($user);

    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();
    $aircraft = Aircraft::factory()->create();

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'phpunit',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(400);
    $body = $response->json();
    expect($body['error']['message'])->toEqual(UserNotAtAirport::MESSAGE);
});

it('can prefile and update a pirep', function () {
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();
    $fare = Fare::factory()->create();

    $user = User::factory()->create(
        [
            'rank_id' => $rank->id,
        ]
    );
    apiAs($user);

    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();
    $aircraft = $subfleet['aircraft']->random();

    app(FareService::class)->setForSubfleet($subfleet, $fare);

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_distance'    => 400,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'UnitTest',
        'fields'              => [
            'custom_field' => 'custom_value',
        ],
        'fares' => [
            [
                'id'    => $fare->id,
                'count' => $fare->capacity,
            ],
        ],
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
    $pirep = $response->json('data');

    expect($pirep['planned_distance']['nmi'])->toEqual(400)
        ->and($pirep['planned_distance']['mi'])->toEqual(460.31)
        ->and($pirep['planned_distance']['km'])->toEqual(740.8)
        ->and($pirep['planned_distance']['m'])->toEqual(740800)
        ->and(str_ends_with($pirep['submitted_at'], 'Z'))->toBeTrue();

    // Are date times in UTC?

    // See that the fields and fares were set
    $fares = PirepFare::where('pirep_id', $pirep['id'])->get();
    expect($fares)->toHaveCount(1);
    $saved_fare = $fares->first();

    // $this->assertEquals($fare->id, $saved_fare['fare_id']);
    expect($saved_fare['count'])->toEqual($fare->capacity)
        ->and($saved_fare['cost'])->toEqual($fare->cost)
        ->and($saved_fare['price'])->toEqual($fare->price);

    // Check saved fields
    $saved_fields = PirepFieldValue::where('pirep_id', $pirep['id'])->get();
    expect($saved_fields)->toHaveCount(1);
    $field = $saved_fields->first();

    expect($field['name'])->toEqual('custom_field')
        ->and($field['value'])->toEqual('custom_value');

    /**
     * Try to update fields
     */
    $uri = '/api/pireps/'.$pirep['id'].'/update';
    $update = [
        'fares' => [
            [
                'id'    => $fare->id,
                'count' => $fare->capacity,
            ],
        ],
    ];

    $response = $this->post($uri, $update);
    $response->assertOk();

    // Make sure there are no duplicates
    $fares = PirepFare::where('pirep_id', $pirep['id'])->get();
    expect($fares)->toHaveCount(1);
    $saved_fare = $fares->first();

    // $this->assertEquals($fare->id, $saved_fare['fare_id']);
    expect($saved_fare['count'])->toEqual($fare->capacity);

    /*
     * Try cancelling the PIREP now
     */
    $uri = '/api/pireps/'.$pirep['id'].'/cancel';
    $response = $this->put($uri, []);
    $response->assertOk();

    // Read it
    $uri = '/api/pireps/'.$pirep['id'];
    $response = $this->get($uri);
    $response->assertOk();
    $body = $response->json('data');

    expect(PirepState::CANCELLED)->toEqual($body['state']);
});

it('should validate updates', function () {
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);
    apiAs($user);

    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();
    $aircraft = $subfleet->aircraft->random();

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_distance'    => 400,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'UnitTest',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
    $pirep = $response->json('data');

    /**
     * Try to update fields
     */
    $uri = '/api/pireps/'.$pirep['id'].'/update';
    $update = [
        'dpt_airport_id' => '',
    ];

    $response = $this->post($uri, $update);
    $response->assertStatus(400);
    $detail = $response->json('details');

    expect($detail)->toEqual('A departure airport is required');
});

it('can receive acars updates', function () {
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);
    apiAs($user);

    $airport = Airport::factory()->create();

    $airline = Airline::factory()->create();

    $aircraft = $subfleet->aircraft->random();

    $uri = '/api/pireps/prefile';
    $pirep_create = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_distance'    => 400,
        'planned_flight_time' => 120,
        'status'              => PirepStatus::BOARDING,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'AcarsTest::testAcarsUpdates',
        'fields'              => [
            'custom_field' => 'custom_value',
        ],
    ];

    $response = $this->post($uri, $pirep_create);
    $response->assertStatus(200);

    // Get the PIREP ID
    $body = $response->json();
    $pirep_id = $body['data']['id'];

    expect($body['data'])->toHaveKeys(['airline', 'arr_airport', 'dpt_airport'])
        ->and($pirep_id)->not->toBeNull()
        ->and($user->id)->toEqual($body['data']['user_id']);

    // Check the PIREP state and status
    $pirep = getPirepFromApi($pirep_id);
    expect($pirep['state'])->toEqual(PirepState::IN_PROGRESS)
        ->and($pirep['status'])->toEqual(PirepStatus::INITIATED)
        ->and($pirep)->toHaveKey('fields')
        ->and($pirep['fields']['custom_field'])->toEqual('custom_value')
        ->and($pirep['planned_distance']['nmi'])->toEqual($pirep_create['planned_distance'])
        ->and($pirep['planned_distance'])->toHaveKeys(['mi', 'nmi', 'km']);

    /*
     * Check the fields
     */

    /**
     * Update the custom field
     */
    $uri = '/api/pireps/'.$pirep_id.'/update';
    $this->post($uri, [
        'flight_time' => 60,
        'distance'    => 20,
        'status'      => PirepStatus::AIRBORNE,
        'fields'      => [
            'custom_field' => 'custom_value_changed',
        ],
    ]);

    $pirep = getPirepFromApi($pirep_id);
    expect($pirep['fields']['custom_field'])->toEqual('custom_value_changed');

    /**
     * Add some position updates
     */
    $uri = '/api/pireps/'.$pirep_id.'/acars/position';

    // Test missing positions field
    // Post an ACARS update
    $update = [];
    $response = $this->post($uri, $update);
    $response->assertStatus(400);

    // Post an ACARS update
    $acars = Acars::factory()->make(['pirep_id' => $pirep_id])->toArray();
    $acars = transformData($acars);

    $update = ['positions' => [$acars]];
    $response = $this->post($uri, $update);
    $response->assertStatus(200)->assertJson(['count' => 1]);

    // Read that if the ACARS record posted
    $response = $this->get($uri);
    $acars_data = $response->json('data')[0];
    expect(round($acars_data['lat'], 2))->toEqual(round($acars['lat'], 2))
        ->and(round($acars_data['lon'], 2))->toEqual(round($acars['lon'], 2))
        ->and($acars_data['log'])->toEqual($acars['log']);

    // Make sure PIREP state moved into ENROUTE
    $pirep = getPirepFromApi($pirep_id);
    expect($pirep['state'])->toEqual(PirepState::IN_PROGRESS)
        ->and($pirep['status'])->toEqual(PirepStatus::AIRBORNE);

    $response = $this->get($uri);
    $response->assertStatus(200);
    $body = $response->json()['data'];

    expect($body)->not->toBeNull()
        ->and($body)->toHaveCount(1)
        ->and(round($body[0]['lat'], 2))->toEqual(round($acars['lat'], 2))
        ->and(round($body[0]['lon'], 2))->toEqual(round($acars['lon'], 2));

    // Update fields standalone
    $uri = '/api/pireps/'.$pirep_id.'/fields';
    $response = $this->post($uri, [
        'fields' => [
            'Departure Gate' => 'G26',
        ],
    ]);

    $response->assertStatus(200);
    $body = $response->json('data');
    expect($body['Departure Gate'])->toEqual('G26');

    /*
     * Get the live flights and make sure all the fields we want are there
     */
    $uri = '/api/acars';
    $response = $this->get($uri);

    $response->assertStatus(200);
    $body = collect($response->json('data'));
    $body = $body->firstWhere('id', $pirep['id']);

    expect($body['user']['name'])->not->toBeEmpty()
        ->and($body['user']['avatar'])->not->toBeEmpty();

    /*
     * File the PIREP
     */
    $uri = '/api/pireps/'.$pirep_id.'/file';
    $response = $this->post($uri, []);
    $response->assertStatus(400);

    // missing field
    $response = $this->post($uri, ['flight_time' => '1:30']);
    $response->assertStatus(400);

    // invalid flight time
    $response = $this->post($uri, [
        'flight_time' => 130,
        'fuel_used'   => 8000.19,
        'distance'    => 400,
    ]);

    $response->assertStatus(200);
    $body = $response->json();

    // Add a comment
    $uri = '/api/pireps/'.$pirep_id.'/comments';
    $response = $this->post($uri, ['comment' => 'A comment']);
    $response->assertStatus(201);

    $response = $this->get($uri);
    $response->assertStatus(200);
    $comments = $response->json();

    expect($comments)->toHaveCount(1);
});

test('multiple altitudes', function () {
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);
    apiAs($user);

    $airport = Airport::factory()->create();

    $airline = Airline::factory()->create();

    $aircraft = $subfleet->aircraft->random();

    $uri = '/api/pireps/prefile';
    $pirep_create = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $aircraft->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_distance'    => 400,
        'planned_flight_time' => 120,
        'status'              => PirepStatus::BOARDING,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'AcarsTest::testAcarsUpdates',
        'fields'              => [
            'custom_field' => 'custom_value',
        ],
    ];

    $response = $this->post($uri, $pirep_create);
    $response->assertStatus(200);

    // Get the PIREP ID
    $body = $response->json();
    $pirep_id = $body['data']['id'];

    $dt = new DateTime('now');

    // Post an ACARS update
    $acars = Acars::factory()->make(['pirep_id' => $pirep_id])->toArray();
    $acars['sim_time'] = $dt->format(DateTime::ATOM);
    unset($acars['altitude_agl']);
    unset($acars['altitude_msl']);
    $acars['altitude'] = 1000;

    $acars = transformData($acars);

    // $acars = $this->transformData($acars);
    $inst = new Acars($acars);
    expect($inst->altitude_agl)->toEqual($acars['altitude'])
        ->and($inst->altitude_msl)->toEqual($acars['altitude']);

    $uri = '/api/pireps/'.$pirep_id.'/acars/position';

    $response = $this->post($uri, ['positions' => [$acars]]);
    $response->assertStatus(200)->assertJson(['count' => 1]);

    // Read that if the ACARS record posted
    $response = $this->get($uri);
    $acars_data = $response->json('data')[0];
    expect($acars_data['altitude_agl'])->toEqual($acars['altitude'])
        ->and($acars_data['altitude_msl'])->toEqual($acars['altitude']);

    /**
     * Now push the new fields without the old one
     */
    $acars2 = Acars::factory()->make(['pirep_id' => $pirep_id])->toArray();
    $acars2['sim_time'] = $dt
        ->add(DateInterval::createFromDateString('30 seconds'))
        ->format(DateTimeInterface::ATOM);

    $acars2 = transformData($acars2);
    // send it in
    $response = $this->post($uri, ['positions' => [$acars2]]);
    $response->assertStatus(200)->assertJson(['count' => 1]);

    // Read that if the ACARS record posted
    $response = $this->get($uri);
    $acars_data = $response->json('data')[1];
    expect($acars_data['altitude_agl'])->toEqualWithDelta($acars2['altitude_agl'], 0.1)
        ->and($acars_data['altitude_msl'])->toEqualWithDelta($acars2['altitude_msl'], 0.1);
});

test('acars data instantitaion', function () {
    $acars = Acars::factory()->make(['pirep_id' => 'abc'])->toArray();
    $acars['altitude'] = 5000;

    $acars = transformData($acars);

    // Set this to the model first
    $inst = new Acars($acars);
    expect($inst->altitude_agl)->toEqual($acars['altitude_agl'])
        ->and($inst->altitude_msl)->toEqual($acars['altitude_msl']);

    // Now delete the agl/msl and recreate the instance
    unset($acars['altitude_agl']);
    unset($acars['altitude_msl']);

    $inst = new Acars($acars);
    expect($inst->altitude_agl)->toEqual($acars['altitude'])
        ->and($inst->altitude_msl)->toEqual($acars['altitude']);
});

it('can file a pirep via api', function () {
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);
    apiAs($user);

    $airport = Airport::factory()->create();

    $airline = Airline::factory()->create();

    $aircraft = $subfleet->aircraft->random();

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'     => $airline->id,
        'aircraft_id'    => $aircraft->id,
        'dpt_airport_id' => $airport->icao,
        'arr_airport_id' => $airport->icao,
        'flight_number'  => '6000',
        'level'          => 38000,
        'source_name'    => 'AcarsTest::testFilePirepApi',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);

    // Get the PIREP ID
    $body = $response->json();
    $pirep_id = $body['data']['id'];

    // File the PIREP now
    $uri = '/api/pireps/'.$pirep_id.'/file';

    $response = $this->post($uri, [
        'flight_time' => 130,
        'fuel_used'   => 8000.19,
        'distance'    => 400,
    ]);

    $response->assertStatus(200);

    // Check the block_off_time and block_on_time being set
    $body = $this->get('/api/pireps/'.$pirep_id)->json('data');
    expect($body['state'])->toEqual(PirepState::PENDING)
        ->and($body['block_off_time'])->not->toBeNull()
        ->and($body['block_on_time'])->not->toBeNull();

    // Try to refile, should be blocked
    $response = $this->post($uri, [
        'flight_time' => 130,
        'fuel_used'   => 8000.19,
        'distance'    => 400,
    ]);

    $response->assertStatus(400);
});

it('should check if the aircraft is allowed for flight', function () {
    updateSetting('pireps.restrict_aircraft_to_rank', true);

    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();

    // Add subfleets and aircraft, but also add another set of subfleets
    $subfleetA = Subfleet::factory()->hasAircraft(1)->create();

    // User not allowed aircraft from this subfleet
    $subfleetB = Subfleet::factory()->hasAircraft(1)->create();

    $rank = Rank::factory()->hasAttached($subfleetA)->create();

    $user = User::factory()->create(
        [
            'rank_id' => $rank->id,
        ]
    );
    apiAs($user);

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $subfleetB->aircraft->random()->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'Unit test',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(400);

    // Try refiling with a valid aircraft
    $pirep['aircraft_id'] = $subfleetA->aircraft->random()->id;
    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
});

it('should ignore aircraft allowed', function () {
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $airport = Airport::factory()->create();
    $airline = Airline::factory()->create();

    // Add subfleets and aircraft, but also add another set of subfleets
    $subfleetA = Subfleet::factory()->hasAircraft(1)->create();

    // User not allowed aircraft from this subfleet
    $subfleetB = Subfleet::factory()->hasAircraft(1)->create();

    $rank = Rank::factory()->hasAttached($subfleetA)->create();

    $user = User::factory()->create(
        [
            'rank_id' => $rank->id,
        ]
    );
    apiAs($user);

    $uri = '/api/pireps/prefile';
    $pirep = [
        'airline_id'          => $airline->id,
        'aircraft_id'         => $subfleetB->aircraft->random()->id,
        'dpt_airport_id'      => $airport->icao,
        'arr_airport_id'      => $airport->icao,
        'flight_number'       => '6000',
        'level'               => 38000,
        'planned_flight_time' => 120,
        'route'               => 'POINTA POINTB',
        'source_name'         => 'Unit test',
    ];

    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
});

it('can receive multiple acars position updates', function () {
    $pirep = createPirep();

    apiAs($pirep->user);
    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';

    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);

    $pirep_id = $response->json()['data']['id'];

    $uri = '/api/pireps/'.$pirep_id.'/acars/position';

    // Post an ACARS update
    $acars_count = random_int(5, 10);
    $acars = Acars::factory()->count($acars_count)->make(['id' => ''])
        ->map(function ($point) {
            $point['id'] = Utils::generateNewId();

            return $point;
        })
        ->toArray();

    $acars = transformData($acars);

    $update = ['positions' => $acars];
    $response = $this->post($uri, $update);
    $response->assertStatus(200)->assertJson(['count' => $acars_count]);

    // Try posting again, should be ignored/not throw any sql errors
    $response = $this->post($uri, $update);
    $response->assertStatus(200)->assertJson(['count' => $acars_count]);

    $response = $this->get($uri);
    $response->assertStatus(200)->assertJsonCount($acars_count, 'data');
});

it('should return a 404 on a non existent pirep get', function () {
    $user = User::factory()->create();
    apiAs($user);

    $uri = '/api/pireps/DOESNTEXIST/acars';
    $response = $this->get($uri);
    $response->assertStatus(404);
});

it('should return a 404 on a non existent pirep store', function () {
    $user = User::factory()->create();
    apiAs($user);

    $uri = '/api/pireps/DOESNTEXIST/acars/position';
    $acars = Acars::factory()->make()->toArray();
    $response = $this->post($uri, $acars);
    $response->assertStatus(404);
});

it('should use acars iso date', function () {
    $pirep = createPirep();
    apiAs($pirep->user);
    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';
    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
    $pirep_id = $response->json()['data']['id'];

    $dt = date('c');
    $uri = '/api/pireps/'.$pirep_id.'/acars/position';
    $acars = Acars::factory()->make([
        'sim_time' => $dt,
    ])->toArray();

    $acars = transformData($acars);

    $update = ['positions' => [$acars]];
    $response = $this->post($uri, $update);
    $response->assertStatus(200);
});

it('refuses invalid route post', function () {
    $pirep = createPirep();
    apiAs($pirep->user);
    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';
    $response = $this->post($uri, $pirep);
    $pirep_id = $response->json()['data']['id'];

    // Missing lat/lon
    $post_route = ['order' => 1, 'name' => 'NAVPOINT'];
    $uri = '/api/pireps/'.$pirep_id.'/route';
    $response = $this->post($uri, $post_route);
    $response->assertStatus(400);

    $post_route = [
        [
            'id'    => 'NAVPOINT',
            'order' => 1,
            'name'  => 'NAVPOINT',
            'lat'   => 'notanumber',
            'lon'   => 34.11,
        ],
    ];

    $uri = '/api/pireps/'.$pirep_id.'/route';
    $response = $this->post($uri, $post_route);
    $response->assertStatus(400);
});

it('can receive acars log', function () {
    $pirep = createPirep();
    apiAs($pirep->user);
    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';
    $response = $this->post($uri, $pirep);
    $pirep_id = $response->json()['data']['id'];

    $acars = Acars::factory()->make();
    $post_log = [
        'logs' => [
            ['log' => $acars->log],
        ],
    ];

    $uri = '/api/pireps/'.$pirep_id.'/acars/logs';
    $response = $this->post($uri, $post_log);
    $response->assertStatus(200);
    $body = $response->json();

    expect($body['count'])->toEqual(1);

    $acars = Acars::factory()->make();
    $post_log = [
        'events' => [
            ['event' => $acars->log],
        ],
    ];

    $uri = '/api/pireps/'.$pirep_id.'/acars/events';
    $response = $this->post($uri, $post_log);
    $response->assertStatus(200);
    $body = $response->json();

    expect($body['count'])->toEqual(1);
});

it('can receive acars route', function () {
    $pirep = createPirep();
    apiAs($pirep->user);
    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';
    $response = $this->post($uri, $pirep);
    $pirep_id = $response->json()['data']['id'];

    $order = 1;
    $post_route = [];
    $route_count = random_int(2, 10);

    $route = Navdata::factory()->count($route_count)->create();
    foreach ($route as $position) {
        $post_route[] = [
            'order' => $order,
            'id'    => $position->id,
            'name'  => $position->id,
            'lat'   => $position->lat,
            'lon'   => $position->lon,
        ];

        $order++;
    }

    $uri = '/api/pireps/'.$pirep_id.'/route';
    $response = $this->post($uri, ['route' => $post_route]);
    $response->assertStatus(200)->assertJson(['count' => $route_count]);

    // Try double post to ignore SQL update
    $response = $this->post($uri, ['route' => $post_route]);
    $response->assertStatus(200)->assertJson(['count' => $route_count]);

    /**
     * Get
     */
    $uri = '/api/pireps/'.$pirep_id.'/route';
    $response = $this->get($uri);
    $response->assertStatus(200)->assertJsonCount($route_count, 'data');

    $body = $response->json()['data'];
    allPointsInRoute($post_route, $body);

    /**
     * Delete and then recheck
     */
    $uri = '/api/pireps/'.$pirep_id.'/route';
    $response = $this->delete($uri);
    $response->assertStatus(200);

    $uri = '/api/pireps/'.$pirep_id.'/route';
    $response = $this->get($uri);
    $response->assertStatus(200)->assertJsonCount(0, 'data');
});

it('handles duplicate pirep', function () {
    $pirep = createPirep();
    apiAs($pirep->user);
    $pirep = $pirep->toArray();
    $pirep = transformData($pirep);

    $uri = '/api/pireps/prefile';
    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
    $pirep_id = $response->json()['data']['id'];

    // try readding
    $response = $this->post($uri, $pirep);
    $response->assertStatus(200);
    $dupe_pirep_id = $response->json()['data']['id'];

    expect($dupe_pirep_id)->toEqual($pirep_id);
});
