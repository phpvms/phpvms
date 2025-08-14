<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Enums\AcarsType;
use App\Models\Enums\FareType;
use App\Models\Enums\UserState;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\SimBriefService;
use App\Support\Utils;
use Carbon\Carbon;

final class SimBriefTest extends TestCase
{
    private static string $simbrief_flight_id = 'simbriefflightid';

    /**
     * @param array $attrs Additional user attributes
     *
     * @throws \Exception
     */
    public function createUserData(array $attrs = []): array
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(2, [$subfleet['subfleet']->id]);

        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'flight_time' => 1000,
            'rank_id'     => $rank->id,
            'state'       => UserState::ACTIVE,
        ], $attrs));

        return [
            'subfleet' => $subfleet['subfleet'],
            'aircraft' => $subfleet['aircraft'],
            'user'     => $user,
        ];
    }

    /**
     * Load SimBrief
     */
    protected function loadSimBrief(User $user, Aircraft $aircraft, array $fares = [], ?string $flight_id = null): SimBrief
    {
        if ($flight_id === null || $flight_id === '' || $flight_id === '0') {
            $flight_id = self::$simbrief_flight_id;
        }

        /** @var Flight $flight */
        $flight = Flight::factory()->create([
            'id'             => $flight_id,
            'dpt_airport_id' => 'OMAA',
            'arr_airport_id' => 'OMDB',
        ]);

        return $this->downloadOfp($user, $flight, $aircraft, $fares);
    }

    /**
     * Download an OFP file
     */
    protected function downloadOfp($user, $flight, $aircraft, $fares): ?SimBrief
    {
        $this->mockXmlResponse([
            'simbrief/briefing.xml',
            'simbrief/acars_briefing.xml',
        ]);

        /** @var SimBriefService $sb */
        $sb = app(SimBriefService::class);

        return $sb->downloadOfp($user->id, Utils::generateNewId(), $flight->id, $aircraft->id, $fares);
    }

    /**
     * Read from the SimBrief URL
     *
     * @throws \Exception
     */
    public function test_read_simbrief(): void
    {
        $userinfo = $this->createUserData();
        $this->user = $userinfo['user'];
        $briefing = $this->loadSimBrief($this->user, $userinfo['aircraft']->first(), []);

        $this->assertNotEmpty($briefing->ofp_xml);
        $this->assertNotNull($briefing->xml);

        // Spot check reading of the files
        $files = $briefing->files;
        $this->assertEquals(47, $files->count());
        $this->assertEquals(
            'http://www.simbrief.com/ofp/flightplans/OMAAOMDB_PDF_1584226092.pdf',
            $files->firstWhere('name', 'PDF Document')['url']
        );

        // Spot check reading of images
        $images = $briefing->images;
        $this->assertEquals(5, $images->count());
        $this->assertEquals(
            'http://www.simbrief.com/ofp/uads/OMAAOMDB_1584226092_ROUTE.gif',
            $images->firstWhere('name', 'Route')['url']
        );

        $level = $briefing->xml->getFlightLevel();
        $this->assertEquals('380', $level);

        // Read the flight route
        $routeStr = $briefing->xml->getRouteString();
        $this->assertEquals(
            'DCT BOMUP DCT LOVIM DCT RESIG DCT NODVI DCT OBMUK DCT LORID DCT '.
            'ORGUR DCT PEBUS DCT EMOPO DCT LOTUK DCT LAGTA DCT LOVOL DCT',
            $routeStr
        );
    }

    /**
     * Check that the API calls are working (simbrief in the response, can retrieve the briefing)
     *
     * @throws \Exception
     */
    public function test_api_calls(): void
    {
        $userinfo = $this->createUserData();
        $this->user = $userinfo['user'];
        $aircraft = $userinfo['aircraft']->random();
        $briefing = $this->loadSimBrief($this->user, $aircraft, [
            [
                'id'       => 100,
                'code'     => 'F',
                'name'     => 'Test Fare',
                'type'     => 'P',
                'capacity' => 100,
                'count'    => 99,
            ],
        ]);

        // Check the flight API response
        $response = $this->get('/api/flights/'.$briefing->flight_id);
        $response->assertOk();
        $flight = $response->json('data');

        $this->assertNotNull($flight['simbrief']);
        $this->assertEquals($briefing->id, $flight['simbrief']['id']);

        $url = str_replace('http://', 'https://', $flight['simbrief']['url']);
        /*$this->assertEquals(
            'https://localhost/api/flights/'.$briefing->id.'/briefing',
            $url
        );*/
        $this->assertTrue(str_ends_with($url, $briefing->id.'/briefing'));

        // Retrieve the briefing via API, and then check the doctype
        $response = $this->get('/api/flights/'.$briefing->id.'/briefing', [], $this->user);
        $response->assertOk();

        $xml = simplexml_load_string($response->content());
        $this->assertNotNull($xml);

        $this->assertEquals('VMSAcars', $xml->getName());
        $this->assertEquals('FlightPlan', $xml->attributes()->Type);
    }

    /**
     * Make sure the user's bids have the Simbrief data show up
     *
     * @throws \Exception
     */
    public function test_user_bid_simbrief(): void
    {
        $fares = [
            [
                'id'       => 100,
                'code'     => 'F',
                'name'     => 'Test Fare',
                'type'     => FareType::PASSENGER,
                'capacity' => 100,
                'count'    => 99,
            ],
        ];

        $userinfo = $this->createUserData();
        $this->user = $userinfo['user'];
        $aircraft = $userinfo['aircraft']->random();
        $this->loadSimBrief($this->user, $aircraft, $fares);

        // Add the flight to the bid and then
        $uri = '/api/user/bids';
        $data = ['flight_id' => self::$simbrief_flight_id];

        $this->put($uri, $data);

        // Retrieve it
        $body = $this->get($uri);
        $body = $body->json('data')[0];

        // Make sure Simbrief is there
        $this->assertNotNull($body['flight']['simbrief']['id']);
        $this->assertNotNull($body['flight']['simbrief']['id']);
        $this->assertNotNull($body['flight']['simbrief']['subfleet']['fares']);

        $subfleet = $body['flight']['simbrief']['subfleet'];
        $this->assertEquals($fares[0]['id'], $subfleet['fares'][0]['id']);
        dd($fares[0], $subfleet['fares'][0]);
        $this->assertEquals($fares[0]['count'], $subfleet['fares'][0]['count']);

        $this->assertCount(1, $subfleet['aircraft']);
        $this->assertEquals($aircraft->id, $subfleet['aircraft'][0]['id']);
    }

    /**
     * Make sure that the bids/simbrief created for the same flight by two different
     * users doesn't leak across users
     *
     * @throws \Exception
     */
    public function test_user_bid_simbrief_doesnt_leak(): void
    {
        $this->updateSetting('bids.disable_flight_on_bid', false);
        $fares = [
            [
                'id'       => 100,
                'code'     => 'F',
                'name'     => 'Test Fare',
                'type'     => FareType::PASSENGER,
                'capacity' => 100,
                'count'    => 99,
            ],
        ];

        /** @var Flight $flight */
        $flight = Flight::factory()->create();

        // Create two briefings and make sure it doesn't leak
        $userinfo2 = $this->createUserData();
        $user2 = $userinfo2['user'];
        $this->downloadOfp($user2, $flight, $userinfo2['aircraft']->first(), $fares);

        $userinfo = $this->createUserData();
        $user = $userinfo['user'];
        $briefing = $this->downloadOfp($user, $flight, $userinfo['aircraft']->first(), $fares);

        // Add the flight to the user's bids
        $uri = '/api/user/bids';
        $data = ['flight_id' => $flight->id];

        // add for both users
        $body = $this->put($uri, $data, [], $user2)->json('data');
        $this->assertNotEmpty($body);

        $body = $this->put($uri, $data, [], $user)->json('data');
        $this->assertNotEmpty($body);

        $body = $this->get('/api/user/bids', [], $user);
        $body = $body->json('data')[0];

        // Make sure Simbrief is there
        $this->assertNotNull($body['flight']['simbrief']['id']);
        $this->assertNotNull($body['flight']['simbrief']['subfleet']['fares']);
        $this->assertEquals($body['flight']['simbrief']['id'], $briefing->id);

        $subfleet = $body['flight']['simbrief']['subfleet'];
        $this->assertEquals($fares[0]['id'], $subfleet['fares'][0]['id']);
        $this->assertEquals($fares[0]['count'], $subfleet['fares'][0]['count']);
    }

    /**
     * @throws \Exception
     */
    public function test_attach_to_pirep(): void
    {
        $userinfo = $this->createUserData();
        $this->user = $userinfo['user'];

        /** @var Pirep $pirep */
        $pirep = Pirep::factory()->create([
            'user_id'        => $this->user->id,
            'dpt_airport_id' => 'OMAA',
            'arr_airport_id' => 'OMDB',
        ]);

        $briefing = $this->loadSimBrief($this->user, $userinfo['aircraft']->first(), [
            [
                'id'       => 100,
                'code'     => 'F',
                'name'     => 'Test Fare',
                'type'     => 'P',
                'capacity' => 100,
                'count'    => 99,
            ],
        ]);

        /** @var SimBriefService $sb */
        $sb = app(SimBriefService::class);
        $sb->attachSimbriefToPirep($pirep, $briefing);

        /*
         * Checks - ACARS entries for the route are loaded
         */
        $acars = Acars::where(['pirep_id' => $pirep->id, 'type' => AcarsType::ROUTE])->get();
        $this->assertEquals(12, $acars->count());

        $fix = $acars->firstWhere('name', 'BOMUP');
        $this->assertEquals('BOMUP', $fix['name']);
        $this->assertEquals(24.484639, $fix['lat']);
        $this->assertEquals(54.578444, $fix['lon']);
        $this->assertEquals(1, $fix['order']);

        $briefing->refresh();

        $this->assertEmpty($briefing->flight_id);
        $this->assertEquals($pirep->id, $briefing->pirep_id);
    }

    /**
     * Test clearing expired briefs
     *
     * @throws \Exception
     */
    public function test_clear_expired_briefs(): void
    {
        $userinfo = $this->createUserData();
        $user = $userinfo['user'];

        $sb_ignored = SimBrief::factory()->create([
            'user_id'    => $user->id,
            'flight_id'  => 'a_flight_id',
            'pirep_id'   => 'a_pirep_id',
            'created_at' => Carbon::now('UTC')->subDays(6),
        ]);

        SimBrief::factory()->create([
            'user_id'    => $user->id,
            'flight_id'  => 'a_flight_Id',
            'created_at' => Carbon::now('UTC')->subDays(6),
        ]);

        /** @var SimBriefService $sb */
        $sb = app(SimBriefService::class);
        $sb->removeExpiredEntries();

        $all_briefs = SimBrief::all();
        $this->assertEquals(1, $all_briefs->count());
        $this->assertEquals($sb_ignored->id, $all_briefs[0]->id);
    }
}
