<?php

namespace Tests;

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
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\FareService;
use App\Support\Utils;

use function count;
use function random_int;

/**
 * Test API calls and authentication, etc
 */
final class AcarsTest extends TestCase
{
    /** @var SettingRepository */
    protected SettingRepository $settingsRepo;

    /** @var FareService */
    protected FareService $fareSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        $this->fareSvc = app(FareService::class);
        $this->settingsRepo = app(SettingRepository::class);
    }

    /**
     * @param array $route
     * @param array $points
     * @param array $addtl_fields
     */
    protected function allPointsInRoute(array $route, array $points, array $addtl_fields = []): void
    {
        if (empty($addtl_fields)) {
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

        $this->assertCount(count($route), $points);
        foreach ($route as $idx => $point) {
            $this->assertHasKeys($points[$idx], $fields);
            foreach ($fields as $f) {
                if ($f === 'lat' || $f === 'lon') {
                    continue;
                }

                $this->assertEquals($point[$f], $points[$idx][$f]);
            }
        }
    }

    protected function getPirep(string $pirep_id): array
    {
        $resp = $this->get('/api/pireps/'.$pirep_id);
        $resp->assertStatus(200);

        return $resp->json()['data'];
    }

    /**
     * Test some prefile error conditions
     */
    public function testPrefileErrors(): void
    {
        $this->user = User::factory()->create();

        $airport = Airport::factory()->create();
        $airline = Airline::factory()->create();
        $aircraft = Aircraft::factory()->create();

        /**
         * INVALID AIRLINE_ID FIELD
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
    }

    public function testPrefileAircraftNotAtAirport(): void
    {
        $this->settingsRepo->store('pilots.only_flights_from_current', false);
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);
        $this->settingsRepo->store('pireps.only_aircraft_at_dpt_airport', true);

        $this->user = User::factory()->create();

        /** @var Airport $airport */
        $airport = Airport::factory()->create();

        /** @var Airport $airport */
        $aircraft_airport = Airport::factory()->create();

        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Aircraft $aircraft */
        $aircraft = Aircraft::factory()->create(['airport_id' => $aircraft_airport->id]);

        /**
         * INVALID AIRLINE_ID FIELD
         */
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
        $this->assertEquals(
            'The aircraft is not at the departure airport',
            $response->json('title')
        );
    }

    public function testBlankAirport(): void
    {
        $this->user = User::factory()->create();

        $airline = Airline::factory()->create();
        $aircraft = Aircraft::factory()->create();

        /**
         * INVALID AIRLINE_ID FIELD
         */
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

        $this->assertEquals(
            'A departure airport is required, An arrival airport is required',
            $response->json('details')
        );
    }

    /**
     * Make sure an error is thrown if the pilot is not at the current airport
     */
    public function testPilotNotAtAirport(): void
    {
        $this->settingsRepo->store('pilots.only_flights_from_current', true);
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);

        $this->user = User::factory()->create([
            'curr_airport_id' => 'KJFK',
        ]);

        $airport = Airport::factory()->create();
        $airline = Airline::factory()->create();
        $aircraft = Aircraft::factory()->create();

        /**
         * INVALID AIRLINE_ID FIELD
         */
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
        $this->assertEquals(UserNotAtAirport::MESSAGE, $body['error']['message']);
    }

    /**
     * Make sure an error is thrown if the pilot is not at the current airport
     */
    public function testAircraftNotAtAirport(): void
    {
        $this->settingsRepo->store('pireps.only_aircraft_at_dpt_airport', true);
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);

        /** @var User user */
        $this->user = User::factory()->create([
            'curr_airport_id' => 'KJFK',
        ]);

        /** @var Airport $airport */
        $airport = Airport::factory()->create();

        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Aircraft $aircraft */
        $aircraft = Aircraft::factory()->create([
            'airport_id' => 'KAUS',
        ]);

        /**
         * INVALID AIRLINE_ID FIELD
         */
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
        $this->assertEquals(AircraftNotAtAirport::MESSAGE, $body['error']['message']);
    }

    /**
     * Post a PIREP into a PREFILE state and post ACARS
     *
     * @throws \Exception
     */
    public function testPrefileAndUpdates(): void
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);

        /** @var Fare $fare */
        $fare = Fare::factory()->create();

        $this->user = User::factory()->create(
            [
                'rank_id' => $rank->id,
            ]
        );

        /** @var Airport $airport */
        $airport = Airport::factory()->create();

        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Aircraft $aircraft */
        $aircraft = $subfleet['aircraft']->random();
        $this->fareSvc->setForSubfleet($subfleet['subfleet'], $fare);

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

        $this->assertEquals(400, $pirep['planned_distance']['nmi']);
        $this->assertEquals(460.31, $pirep['planned_distance']['mi']);
        $this->assertEquals(740.8, $pirep['planned_distance']['km']);
        $this->assertEquals(740800, $pirep['planned_distance']['m']);

        // Are date times in UTC?
        $this->assertTrue(str_ends_with($pirep['submitted_at'], 'Z'));

        // See that the fields and fares were set
        $fares = PirepFare::where('pirep_id', $pirep['id'])->get();
        $this->assertCount(1, $fares);
        $saved_fare = $fares->first();

        // $this->assertEquals($fare->id, $saved_fare['fare_id']);
        $this->assertEquals($fare->capacity, $saved_fare['count']);
        $this->assertEquals($fare->cost, $saved_fare['cost']);
        $this->assertEquals($fare->price, $saved_fare['price']);

        // Check saved fields
        $saved_fields = PirepFieldValue::where('pirep_id', $pirep['id'])->get();
        $this->assertCount(1, $saved_fields);
        $field = $saved_fields->first();

        $this->assertEquals('custom_field', $field['name']);
        $this->assertEquals('custom_value', $field['value']);

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
        $this->assertCount(1, $fares);
        $saved_fare = $fares->first();

        // $this->assertEquals($fare->id, $saved_fare['fare_id']);
        $this->assertEquals($fare->capacity, $saved_fare['count']);

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

        $this->assertEquals($body['state'], PirepState::CANCELLED);
    }

    /**
     * @throws \Exception
     */
    public function testPrefileAndInvalidUpdates(): void
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);

        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        $airport = Airport::factory()->create();
        $airline = Airline::factory()->create();
        $aircraft = $subfleet['aircraft']->random();

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

        $this->assertEquals('A departure airport is required', $detail);
    }

    /**
     * Post a PIREP into a PREFILE state and post ACARS
     *
     * @throws \Exception
     */
    public function testAcarsUpdates(): void
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);

        /** @var User user */
        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        /** @var Airport $airport */
        $airport = Airport::factory()->create();

        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Aircraft $aircraft */
        $aircraft = $subfleet['aircraft']->random();

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

        $this->assertHasKeys($body['data'], ['airline', 'arr_airport', 'dpt_airport']);
        $this->assertNotNull($pirep_id);
        $this->assertEquals($body['data']['user_id'], $this->user->id);

        // Check the PIREP state and status
        $pirep = $this->getPirep($pirep_id);
        $this->assertEquals(PirepState::IN_PROGRESS, $pirep['state']);
        $this->assertEquals(PirepStatus::INITIATED, $pirep['status']);

        /*
         * Check the fields
         */
        $this->assertHasKeys($pirep, ['fields']);
        $this->assertEquals('custom_value', $pirep['fields']['custom_field']);
        $this->assertEquals($pirep_create['planned_distance'], $pirep['planned_distance']['nmi']);
        $this->assertHasKeys($pirep['planned_distance'], ['mi', 'nmi', 'km']);

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

        $pirep = $this->getPirep($pirep_id);
        $this->assertEquals('custom_value_changed', $pirep['fields']['custom_field']);

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
        $acars = $this->transformData($acars);

        $update = ['positions' => [$acars]];
        $response = $this->post($uri, $update);
        $response->assertStatus(200)->assertJson(['count' => 1]);

        // Read that if the ACARS record posted
        $response = $this->get($uri);
        $acars_data = $response->json('data')[0];
        $this->assertEquals(round($acars['lat'], 2), round($acars_data['lat'], 2));
        $this->assertEquals(round($acars['lon'], 2), round($acars_data['lon'], 2));
        $this->assertEquals($acars['log'], $acars_data['log']);

        // Make sure PIREP state moved into ENROUTE
        $pirep = $this->getPirep($pirep_id);
        $this->assertEquals(PirepState::IN_PROGRESS, $pirep['state']);
        $this->assertEquals(PirepStatus::AIRBORNE, $pirep['status']);

        $response = $this->get($uri);
        $response->assertStatus(200);
        $body = $response->json()['data'];

        $this->assertNotNull($body);
        $this->assertCount(1, $body);
        $this->assertEquals(round($acars['lat'], 2), round($body[0]['lat'], 2));
        $this->assertEquals(round($acars['lon'], 2), round($body[0]['lon'], 2));

        // Update fields standalone
        $uri = '/api/pireps/'.$pirep_id.'/fields';
        $response = $this->post($uri, [
            'fields' => [
                'Departure Gate' => 'G26',
            ],
        ]);

        $response->assertStatus(200);
        $body = $response->json('data');
        $this->assertEquals('G26', $body['Departure Gate']);

        /*
         * Get the live flights and make sure all the fields we want are there
         */
        $uri = '/api/acars';
        $response = $this->get($uri);

        $response->assertStatus(200);
        $body = collect($response->json('data'));
        $body = $body->firstWhere('id', $pirep['id']);

        $this->assertNotEmpty($body['user']['name']);
        $this->assertNotEmpty($body['user']['avatar']);

        /*
         * File the PIREP
         */

        $uri = '/api/pireps/'.$pirep_id.'/file';
        $response = $this->post($uri, []);
        $response->assertStatus(400); // missing field

        $response = $this->post($uri, ['flight_time' => '1:30']);
        $response->assertStatus(400); // invalid flight time

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

        $this->assertCount(1, $comments);
    }

    /**
     * Post a PIREP into a PREFILE state and post ACARS
     *
     * @throws \Exception
     */
    public function testFilePirepApi(): void
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);

        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        /** @var Airport $airport */
        $airport = Airport::factory()->create();

        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Aircraft $aircraft */
        $aircraft = $subfleet['aircraft']->random();

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
        $this->assertEquals(PirepState::PENDING, $body['state']);
        $this->assertNotNull($body['block_off_time']);
        $this->assertNotNull($body['block_on_time']);

        // Try to refile, should be blocked
        $response = $this->post($uri, [
            'flight_time' => 130,
            'fuel_used'   => 8000.19,
            'distance'    => 400,
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test aircraft is allowed
     *
     * @throws \Exception
     */
    public function testAircraftAllowed(): void
    {
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', true);

        $airport = Airport::factory()->create();
        $airline = Airline::factory()->create();

        // Add subfleets and aircraft, but also add another set of subfleets
        $subfleetA = $this->createSubfleetWithAircraft(1);

        // User not allowed aircraft from this subfleet
        $subfleetB = $this->createSubfleetWithAircraft(1);

        $rank = $this->createRank(10, [$subfleetA['subfleet']->id]);

        $this->user = User::factory()->create(
            [
                'rank_id' => $rank->id,
            ]
        );

        $uri = '/api/pireps/prefile';
        $pirep = [
            'airline_id'          => $airline->id,
            'aircraft_id'         => $subfleetB['aircraft']->random()->id,
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
        $pirep['aircraft_id'] = $subfleetA['aircraft']->random()->id;
        $response = $this->post($uri, $pirep);
        $response->assertStatus(200);
    }

    /**
     * Test aircraft permissions being ignored
     *
     * @throws \Exception
     */
    public function testIgnoreAircraftAllowed(): void
    {
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);

        $airport = Airport::factory()->create();
        $airline = Airline::factory()->create();

        // Add subfleets and aircraft, but also add another set of subfleets
        $subfleetA = $this->createSubfleetWithAircraft(1);

        // User not allowed aircraft from this subfleet
        $subfleetB = $this->createSubfleetWithAircraft(1);

        $rank = $this->createRank(10, [$subfleetA['subfleet']->id]);

        $this->user = User::factory()->create(
            [
                'rank_id' => $rank->id,
            ]
        );

        $uri = '/api/pireps/prefile';
        $pirep = [
            'airline_id'          => $airline->id,
            'aircraft_id'         => $subfleetB['aircraft']->random()->id,
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
    }

    /**
     * Test publishing multiple, batched updates
     *
     * @throws \Exception
     */
    public function testMultipleAcarsPositionUpdates(): void
    {
        $pirep = $this->createPirep()->toArray();

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

        $update = ['positions' => $acars];
        $response = $this->post($uri, $update);
        $response->assertStatus(200)->assertJson(['count' => $acars_count]);

        // Try posting again, should be ignored/not throw any sql errors
        $response = $this->post($uri, $update);
        $response->assertStatus(200)->assertJson(['count' => $acars_count]);

        $response = $this->get($uri);
        $response->assertStatus(200)->assertJsonCount($acars_count, 'data');
    }

    public function testNonExistentPirepGet(): void
    {
        $this->user = User::factory()->create();

        $uri = '/api/pireps/DOESNTEXIST/acars';
        $response = $this->get($uri);
        $response->assertStatus(404);
    }

    public function testNonExistentPirepStore(): void
    {
        $this->user = User::factory()->create();

        $uri = '/api/pireps/DOESNTEXIST/acars/position';
        $acars = Acars::factory()->make()->toArray();
        $response = $this->post($uri, $acars);
        $response->assertStatus(404);
    }

    /**
     * @throws \Exception
     */
    public function testAcarsIsoDate(): void
    {
        $pirep = $this->createPirep()->toArray();

        $uri = '/api/pireps/prefile';
        $response = $this->post($uri, $pirep);
        $pirep_id = $response->json()['data']['id'];

        $dt = date('c');
        $uri = '/api/pireps/'.$pirep_id.'/acars/position';
        $acars = Acars::factory()->make([
            'sim_time' => $dt,
        ])->toArray();

        $update = ['positions' => [$acars]];
        $response = $this->post($uri, $update);
        $response->assertStatus(200);
    }

    /**
     * Test the validation
     *
     * @throws \Exception
     */
    public function testAcarsInvalidRoutePost(): void
    {
        $pirep = $this->createPirep()->toArray();

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
    }

    /**
     * @throws \Exception
     */
    public function testAcarsLogPost(): void
    {
        $pirep = $this->createPirep()->toArray();

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

        $this->assertEquals(1, $body['count']);

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

        $this->assertEquals(1, $body['count']);
    }

    /**
     * @throws \Exception
     */
    public function testAcarsRoutePost(): void
    {
        $pirep = $this->createPirep()->toArray();

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
        $this->allPointsInRoute($post_route, $body);

        /**
         * Delete and then recheck
         */
        $uri = '/api/pireps/'.$pirep_id.'/route';
        $response = $this->delete($uri);
        $response->assertStatus(200);

        $uri = '/api/pireps/'.$pirep_id.'/route';
        $response = $this->get($uri);
        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    /**
     * Try to refile the same PIREP
     *
     * @throws \Exception
     */
    public function testDuplicatePirep(): void
    {
        $pirep = $this->createPirep()->toArray();

        $uri = '/api/pireps/prefile';
        $response = $this->post($uri, $pirep);
        $response->assertStatus(200);
        $pirep_id = $response->json()['data']['id'];

        // try readding
        $response = $this->post($uri, $pirep);
        $response->assertStatus(200);
        $dupe_pirep_id = $response->json()['data']['id'];

        $this->assertEquals($pirep_id, $dupe_pirep_id);
    }
}
