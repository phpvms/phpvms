<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Http\Controllers\Api\MapController;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepArchive;
use App\Models\PirepEvent;
use App\Models\PirepPosition;
use App\Models\User;
use App\Support\MapTrackSimplifier;
use Illuminate\Support\Facades\DB;

/** A pirep on the live map: `Pirep::scopeOnLiveMap()` inner-joins `pirep_positions`. */
function mapLiveFlight(array $pirepAttrs = [], array $positionAttrs = []): Pirep
{
    $pirep = Pirep::factory()->create(array_merge([
        'state'  => PirepState::IN_PROGRESS,
        'status' => PirepPhase::ENROUTE,
    ], $pirepAttrs));

    PirepPosition::factory()->create(array_merge([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
    ], $positionAttrs));

    return $pirep;
}

/** Bind a MapController with small caps so a cap test doesn't need hundreds of rows. */
function bindCappedMapController(int $maxLiveFlights = 500, int $maxTrackPoints = 5000, int $maxEvents = 200): void
{
    app()->instance(MapController::class, new MapController(
        app(MapTrackSimplifier::class),
        maxLiveFlights: $maxLiveFlights,
        maxTrackPoints: $maxTrackPoints,
        maxEvents: $maxEvents,
    ));
}

test('the live index returns one lean entry per flight, with no track points', function (): void {
    $pirep = mapLiveFlight(positionAttrs: [
        'lat' => 41.5, 'lon' => -87.25, 'heading' => 270, 'altitude_msl' => 33000,
    ]);

    $response = test()->getJson('/api/map/live')->assertOk();
    $entries = collect($response->json());
    $entry = $entries->firstWhere('pirepId', $pirep->id);

    expect($entry)->not->toBeNull()
        ->and($entry['position']['lat'])->toEqual(41.5)
        ->and($entry['position']['lon'])->toEqual(-87.25)
        ->and($entry['position']['heading'])->toEqual(270)
        ->and($entry['position']['altitude'])->toEqual(33000);

    // Exact shape, not merely a subset — an added field (email, a financial
    // figure, an internal log) must fail this test, not slip through.
    expect(array_keys($entry))->toEqualCanonicalizing([
        'pirepId', 'ident', 'status', 'phase', 'progress',
        'pilot', 'airline', 'aircraft', 'dptAirport', 'arrAirport', 'position',
    ]);
    expect(array_keys($entry['pilot']))->toEqualCanonicalizing(['id', 'name', 'ident']);
    expect(array_keys($entry['airline']))->toEqualCanonicalizing(['icao', 'name', 'logo']);
    expect(array_keys($entry['aircraft']))->toEqualCanonicalizing(['id', 'registration', 'name']);
    expect(array_keys($entry['dptAirport']))->toEqualCanonicalizing(['icao', 'name']);
    expect(array_keys($entry['arrAirport']))->toEqualCanonicalizing(['icao', 'name']);
    expect(array_keys($entry['position']))->toEqualCanonicalizing(['lat', 'lon', 'altitude', 'heading']);
});

test('the index is self-sufficient for markers and a flight list, with no duplicate geometry', function (): void {
    $pirep = mapLiveFlight();

    $entry = collect(test()->getJson('/api/map/live')->json())->firstWhere('pirepId', $pirep->id);

    expect($entry['pilot']['name'])->not->toBeEmpty()
        ->and($entry['airline']['icao'])->not->toBeEmpty()
        ->and($entry['aircraft']['registration'])->not->toBeEmpty()
        ->and($entry['dptAirport']['icao'])->not->toBeEmpty()
        ->and($entry['arrAirport']['icao'])->not->toBeEmpty();

    // No GeoJSON FeatureCollection duplicating the position field — asserted
    // by the exact top-level key set in the previous test, not repeated here.
    expect($entry)->not->toHaveKey('geometry')
        ->and($entry)->not->toHaveKey('type');
});

test('a pirep with no position row is omitted from the index rather than emitted with nulls', function (): void {
    $onMap = mapLiveFlight();

    // In progress, but no position row — scopeOnLiveMap's inner join excludes it.
    $offMap = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS, 'status' => PirepPhase::ENROUTE]);

    $ids = collect(test()->getJson('/api/map/live')->json())->pluck('pirepId');

    expect($ids)->toContain($onMap->id)
        ->and($ids)->not->toContain($offMap->id);
});

test('anonymous access to the live index succeeds', function (): void {
    test()->getJson('/api/map/live')->assertOk();
});

test('the live index is truncated at its configured cap, most-recently-updated first', function (): void {
    bindCappedMapController(maxLiveFlights: 2);

    $stale = mapLiveFlight();
    $middle = mapLiveFlight();
    $freshest = mapLiveFlight();

    // `updated_at` isn't in PirepPosition::$fillable, and an Eloquent save()
    // would re-touch it anyway — set it with the query builder directly,
    // which does neither.
    DB::table('pirep_positions')->where('pirep_id', $stale->id)->update(['updated_at' => now('UTC')->subMinutes(10)]);
    DB::table('pirep_positions')->where('pirep_id', $middle->id)->update(['updated_at' => now('UTC')->subMinutes(5)]);
    DB::table('pirep_positions')->where('pirep_id', $freshest->id)->update(['updated_at' => now('UTC')]);

    $ids = collect(test()->getJson('/api/map/live')->json())->pluck('pirepId');

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($freshest->id)
        ->and($ids)->toContain($middle->id)
        ->and($ids)->not->toContain($stale->id);
});

test('the detail payload carries the flown track, planned fixes, airports, and events for one flight', function (): void {
    $pirep = mapLiveFlight(pirepAttrs: ['level' => 350]);

    foreach ([[10.0, -70.0, 5000], [10.5, -70.5, 20000], [11.0, -71.0, 35000]] as $i => [$lat, $lon, $alt]) {
        Acars::factory()->create([
            'pirep_id'     => $pirep->id,
            'type'         => AcarsType::FLIGHT_PATH,
            'lat'          => $lat,
            'lon'          => $lon,
            'altitude_msl' => $alt,
            'sim_time'     => now('UTC')->addMinutes($i)->toIso8601String(),
        ]);
    }

    PirepArchive::create([
        'pirep_id' => $pirep->id,
        'navlog'   => [
            ['ident' => 'FIXA', 'type' => 'wpt', 'pos_lat' => 10.1, 'pos_long' => -70.1, 'altitude_feet' => 15000],
            ['ident' => 'FIXB', 'type' => 'wpt', 'pos_lat' => 10.6, 'pos_long' => -70.6, 'altitude_feet' => 35000],
        ],
    ]);

    PirepEvent::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => 'pushback',
        'phase'    => PirepPhase::PUSHBACK_TOW->value,
    ]);

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['pirepId'])->toEqual($pirep->id)
        ->and($body['flown']['points'])->not->toBeEmpty()
        ->and($body['planned']['fixes'])->toHaveCount(2)
        ->and($body['planned']['fixes'][0]['ident'])->toEqual('FIXA')
        ->and($body['planned']['fixes'][0]['altitudeFt'])->toEqual(15000)
        ->and($body['cruiseLevel'])->toEqual(350)
        ->and($body['events'])->toHaveCount(1)
        ->and($body['events'][0]['type'])->toEqual('pushback');

    // Exact shape at every level — a `toHaveKeys()` subset check would not
    // fail if pilot email, a financial field, or a raw internal log were
    // added to any of these.
    expect(array_keys($body))->toEqualCanonicalizing([
        'pirepId', 'ident', 'callsign', 'status', 'phase', 'pilot', 'airline', 'aircraft',
        'airports', 'scheduledArrivalAt', 'blockOffTime', 'blockOnTime', 'plannedFlightTime',
        'flightTime', 'fuelUsed', 'blockFuel', 'distance', 'plannedDistance', 'cruiseLevel',
        'remarks', 'flown', 'planned', 'events',
    ]);
    expect(array_keys($body['airports']))->toEqualCanonicalizing(['dpt', 'arr', 'alt']);
    expect(array_keys($body['flown']))->toEqualCanonicalizing(['points']);
    expect(array_keys($body['flown']['points'][0]))->toEqualCanonicalizing(['lat', 'lon', 'altitude', 'phase']);
    expect(array_keys($body['planned']))->toEqualCanonicalizing(['fixes', 'fallbackAltitudeFt']);
    expect(array_keys($body['planned']['fixes'][0]))->toEqualCanonicalizing(['ident', 'lat', 'lon', 'altitudeFt', 'viaAirway', 'isSidStar']);
    expect(array_keys($body['events'][0]))->toEqualCanonicalizing(['type', 'phase', 'lat', 'lon', 'altitude', 'occurredAt']);
});

test('a pirep with no navlog returns empty planned fixes but still carries the cruise level', function (): void {
    $pirep = mapLiveFlight(pirepAttrs: ['level' => 380]);

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['planned']['fixes'])->toEqual([])
        ->and($body['cruiseLevel'])->toEqual(380)
        ->and($body['flown']['points'])->toEqual([]);
});

test('a previously archived navlog with no per-fix altitude reports null altitude, cruise level still present', function (): void {
    $pirep = mapLiveFlight(pirepAttrs: ['level' => 340]);

    // Pre-altitude-retention shape: no `altitude_feet` key at all.
    PirepArchive::create([
        'pirep_id' => $pirep->id,
        'navlog'   => [
            ['ident' => 'OLDFIX', 'type' => 'wpt', 'pos_lat' => 5.0, 'pos_long' => 6.0],
        ],
    ]);

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['planned']['fixes'])->toHaveCount(1)
        ->and($body['planned']['fixes'][0]['altitudeFt'])->toBeNull()
        ->and($body['cruiseLevel'])->toEqual(340);
});

test('the flown track is truncated at its configured cap before simplification', function (): void {
    $pirep = mapLiveFlight();

    // A tolerance of 0 keeps every point whose 3D position isn't exactly
    // collinear with its neighbours. Lat/lon/altitude are transcendental
    // functions of the index (sin/cos, no periodicity an integer index could
    // land exactly on), verified empirically to leave all 10 raw points
    // standing when nothing truncates them — so if fewer than 5 come back
    // here, the cap, not simplification, is what's cutting them.
    // Bind the zero-tolerance simplifier first — bindCappedMapController()
    // resolves it from the container when building the capped controller.
    app()->instance(MapTrackSimplifier::class, new MapTrackSimplifier(toleranceMeters: 0.0));
    bindCappedMapController(maxTrackPoints: 5);

    foreach (range(0, 9) as $i) {
        Acars::factory()->create([
            'pirep_id'     => $pirep->id,
            'type'         => AcarsType::FLIGHT_PATH,
            'lat'          => $i * 0.7 + sin($i) * 0.01,
            'lon'          => cos($i * 1.9) * 3.0,
            'altitude_msl' => 1000.0 * ($i * $i) + sin($i * 2.3) * 50.0,
            'sim_time'     => now('UTC')->addMinutes($i)->toIso8601String(),
        ]);
    }

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['flown']['points'])->toHaveCount(5);
});

test('events are truncated at their configured cap', function (): void {
    bindCappedMapController(maxEvents: 3);

    $pirep = mapLiveFlight();

    foreach (range(0, 5) as $i) {
        PirepEvent::factory()->create([
            'pirep_id'   => $pirep->id,
            'created_at' => now('UTC')->addSeconds($i),
        ]);
    }

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['events'])->toHaveCount(3);
});

test('anonymous access to the detail endpoint is refused for a PIREP not on the live map', function (): void {
    // Covers exactly what the exposure the review found: pending, rejected,
    // and long-accepted PIREPs, none with a position row, must all refuse an
    // anonymous caller rather than leak pilot identity, block times, fuel
    // figures, and remarks.
    $pending = Pirep::factory()->create(['state' => PirepState::PENDING]);
    $rejected = Pirep::factory()->create(['state' => PirepState::REJECTED]);
    $accepted = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);

    foreach ([$pending, $rejected, $accepted] as $pirep) {
        test()->getJson('/api/map/pirep/'.$pirep->id)->assertStatus(401);
    }
});

test('anonymous access to the detail endpoint succeeds for a PIREP on the live map', function (): void {
    $pirep = mapLiveFlight();

    test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk();
});

test('authenticated access to the detail endpoint succeeds for a PIREP not on the live map', function (): void {
    // The admin PIREP view and the skylight briefing both need this: an
    // authenticated browser session, no position row required.
    $pirep = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    $user = User::factory()->create();

    test()->actingAs($user)->getJson('/api/map/pirep/'.$pirep->id)->assertOk();
});

test('an unknown pirep 404s on the detail endpoint', function (): void {
    test()->getJson('/api/map/pirep/DOESNTEXIST')->assertNotFound();
});
