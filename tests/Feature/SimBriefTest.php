<?php

use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Enums\AcarsType;
use App\Models\Enums\FareType;
use App\Models\Enums\UserState;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\SimBrief;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\SimBriefService;
use App\Support\Utils;
use Carbon\Carbon;

/**
 * @param array $attrs Additional user attributes
 *
 * @throws Exception
 */
function createUserData(array $attrs = []): array
{
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    /** @var User $user */
    $user = User::factory()->create(array_merge([
        'flight_time' => 1000,
        'rank_id'     => $rank->id,
        'state'       => UserState::ACTIVE,
    ], $attrs));

    return [
        'subfleet' => $subfleet,
        'aircraft' => $subfleet->aircraft,
        'user'     => $user,
    ];
}

/**
 * Load SimBrief
 */
function loadSimBrief(User $user, Aircraft $aircraft, array $fares = [], ?string $flight_id = null): SimBrief
{
    if (in_array($flight_id, [null, '', '0'], true)) {
        $flight_id = 'FLIGHT_ID_1';
    }

    /** @var Flight $flight */
    $flight = Flight::factory()->create([
        'id'             => $flight_id,
        'dpt_airport_id' => 'OMAA',
        'arr_airport_id' => 'OMDB',
    ]);

    return downloadOfp($user, $flight, $aircraft, $fares);
}

/**
 * Download an OFP file
 */
function downloadOfp($user, $flight, $aircraft, $fares): ?SimBrief
{

    Illuminate\Support\Facades\Http::fake([
        'simbrief.com/*' => Http::response(readDataFile('simbrief/briefing.json'), 200, ['Content-Type' => 'application/json']),
    ]);

    return app(SimBriefService::class)->downloadOfp($user, 'static_id', Utils::generateNewId(), $flight->id, $aircraft->id, $fares);
}

test('read simbrief', function () {
    $userinfo = createUserData();
    $user = $userinfo['user'];
    $briefing = loadSimBrief($user, $userinfo['aircraft']->first(), []);

    expect($briefing->ofp_json_path)->not->toBeEmpty()
        ->and($briefing->ofp)->not->toBeNull();

    // Spot check reading of the files
    $files = $briefing->files;
    expect($files->count())->toEqual(67)
        ->and($files->firstWhere('name',
            'PDF Document')['url'])->toEqual('https://www.simbrief.com/ofp/flightplans/OMAAOMDB_PDF_1584226092.pdf');

    // Spot check reading of images
    $images = $briefing->images;
    expect($images->count())->toEqual(6)
        ->and($images->firstWhere('name',
            'Route')['url'])->toEqual('https://www.simbrief.com/ofp/uads/OMAAOMDB_UAD_1584226092_ROUTE.gif');

    $level = $briefing->ofp->general->initial_altitude;
    expect($level)->toEqual(9000);

    // Read the flight route
    $routeStr = $briefing->ofp->general->route;
    expect($routeStr)->toEqual('DCT BOMUP DCT LOVIM DCT RESIG DCT NODVI DCT OBMUK DCT LORID DCT '.
    'ORGUR DCT PEBUS DCT EMOPO DCT LOTUK DCT LAGTA DCT LOVOL');
});

test('api calls', function () {
    $userinfo = createUserData();
    $user = $userinfo['user'];

    apiAs($user);

    $aircraft = $userinfo['aircraft']->random();
    $briefing = loadSimBrief($user, $aircraft, [
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

    expect($flight['simbrief'])->not->toBeNull()
        ->and($flight['simbrief']['id'])->toEqual($briefing->id);

    $url = str_replace('http://', 'https://', $flight['simbrief']['url']);

    /*$this->assertEquals(
          'https://localhost/api/flights/'.$briefing->id.'/briefing',
          $url
      );*/
    expect(str_ends_with($url, $briefing->id.'/briefing'))->toBeTrue();

    // Retrieve the briefing via API, and then check the doctype
    $response = $this->get('/api/flights/'.$briefing->id.'/briefing');
    $response->assertOk();

    $json = $response->json();

    expect($json)->not->toBeEmpty();
});

test('user bid simbrief', function () {
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

    $userinfo = createUserData();
    $user = $userinfo['user'];
    apiAs($user);
    $aircraft = $userinfo['aircraft']->random();
    loadSimBrief($user, $aircraft, $fares);

    // Add the flight to the bid and then
    $uri = '/api/user/bids';
    $data = ['flight_id' => 'FLIGHT_ID_1'];

    $this->put($uri, $data);

    // Retrieve it
    $body = $this->get($uri);
    $body = $body->json('data')[0];

    // Make sure Simbrief is there
    expect($body['flight']['simbrief']['id'])->not->toBeNull()
        ->and($body['flight']['simbrief']['id'])->not->toBeNull()
        ->and($body['flight']['simbrief']['subfleet']['fares'])->not->toBeNull();

    $subfleet = $body['flight']['simbrief']['subfleet'];
    expect($subfleet['fares'][0]['id'])->toEqual($fares[0]['id'])
        ->and($subfleet['fares'][0]['count'])->toEqual($fares[0]['count'])
        ->and($subfleet['aircraft'])->toHaveCount(1)
        ->and($subfleet['aircraft'][0]['id'])->toEqual($aircraft->id);

});

test('user bid simbrief doesnt leak', function () {
    updateSetting('bids.disable_flight_on_bid', false);
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
    $userinfo2 = createUserData();
    $user2 = $userinfo2['user'];
    downloadOfp($user2, $flight, $userinfo2['aircraft']->first(), $fares);

    $userinfo = createUserData();
    $user = $userinfo['user'];
    apiAs($user);

    $briefing = downloadOfp($user, $flight, $userinfo['aircraft']->first(), $fares);

    // Add the flight to the user's bids
    $uri = '/api/user/bids';
    $data = ['flight_id' => $flight->id];

    // add for both users
    apiAs($user2);
    $body = $this->put($uri, $data)->json('data');
    expect($body)->not->toBeEmpty();

    apiAs($user);
    $body = $this->put($uri, $data)->json('data');
    expect($body)->not->toBeEmpty();

    $body = $this->get('/api/user/bids');
    $body = $body->json('data')[0];

    // Make sure Simbrief is there
    expect($body['flight']['simbrief']['id'])->not->toBeNull()
        ->and($body['flight']['simbrief']['subfleet']['fares'])->not->toBeNull()
        ->and($briefing->id)->toEqual($body['flight']['simbrief']['id']);

    $subfleet = $body['flight']['simbrief']['subfleet'];
    expect($subfleet['fares'][0]['id'])->toEqual($fares[0]['id'])
        ->and($subfleet['fares'][0]['count'])->toEqual($fares[0]['count']);
});

test('attach to pirep', function () {
    $userinfo = createUserData();
    $user = $userinfo['user'];

    /** @var Pirep $pirep */
    $pirep = Pirep::factory()->create([
        'user_id'        => $user->id,
        'dpt_airport_id' => 'OMAA',
        'arr_airport_id' => 'OMDB',
    ]);

    $briefing = loadSimBrief($user, $userinfo['aircraft']->first(), [
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
    expect($acars->count())->toEqual(12);

    $fix = $acars->firstWhere('name', 'BOMUP');
    expect($fix['name'])->toEqual('BOMUP')
        ->and($fix['lat'])->toEqual(24.484639)
        ->and($fix['lon'])->toEqual(54.578444)
        ->and($fix['order'])->toEqual(1);

    $briefing->refresh();

    expect($briefing->flight_id)->toBeEmpty()
        ->and($briefing->pirep_id)->toEqual($pirep->id);
});

test('clear expired briefs', function () {
    $userinfo = createUserData();
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
    expect($all_briefs->count())->toEqual(1)
        ->and($all_briefs[0]->id)->toEqual($sb_ignored->id);
});
