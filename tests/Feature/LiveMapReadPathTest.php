<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Models\User;
use App\Services\GeoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The trail's coordinates, in order. `getLine()` hands back a FeatureCollection
 * holding a single LineString feature, and returns an empty one when there are
 * fewer than two points to join.
 */
function trailCoordinates(array $geo): array
{
    $features = $geo['line']->getFeatures();

    return $features === [] ? [] : $features[0]->getGeometry()->getCoordinates();
}

function flightOnLiveMap(array $pirepAttrs = [], array $positionAttrs = []): Pirep
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

test('a flight is on the map if and only if it has a position row', function (): void {
    $onMap = flightOnLiveMap();

    // In progress, but no position row: not on the map.
    $offMap = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    // Finished, but its row has not been evicted yet: still on the map. Under
    // the old query this was filtered out by `state = IN_PROGRESS`.
    $completed = flightOnLiveMap(['state' => PirepState::PENDING]);

    $ids = collect(test()->get('/api/acars')->json('data'))->pluck('id');

    expect($ids)->toContain($onMap->id)
        ->and($ids)->toContain($completed->id)
        ->and($ids)->not->toContain($offMap->id);
});

test('the response still carries the nested position object', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: ['lat' => 41.5, 'lon' => -87.25, 'heading' => 270]);

    $flight = collect(test()->get('/api/acars')->json('data'))
        ->firstWhere('id', $pirep->id);

    expect($flight)->toHaveKey('position')
        ->and($flight['position']['lat'])->toEqual(41.5)
        ->and($flight['position']['lon'])->toEqual(-87.25)
        ->and($flight['position']['heading'])->toBe(270)
        ->and($flight['position']['distance'])->toHaveKeys(['m', 'km', 'mi', 'nmi']);
});

test('the GeoJSON endpoint draws a point per flight on the map', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: [
        'lat' => 12.5, 'lon' => -30.25, 'heading' => 90, 'altitude_msl' => 33000,
    ]);

    $features = test()->get('/api/acars/geojson')->json('data.features');
    $feature = collect($features)->firstWhere('properties.pirep_id', $pirep->id);

    expect($feature['geometry']['coordinates'])->toEqual([-30.25, 12.5, 33000])
        ->and($feature['properties']['heading'])->toBe(90);
});

test('a flight with thousands of breadcrumbs costs no extra queries', function (): void {
    $busy = flightOnLiveMap();

    Acars::factory()->count(50)->create([
        'pirep_id' => $busy->id,
        'type'     => AcarsType::FLIGHT_PATH,
    ]);

    flightOnLiveMap();
    flightOnLiveMap();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    test()->get('/api/acars')->assertOk();

    // The whole point of the change: no per-PIREP latest-row lookup against
    // `acars` on the live map path, so the cost of drawing a flight does not
    // grow with the length of its track.
    $acarsQueries = array_filter($queries, fn (string $sql): bool => str_contains($sql, '"acars"') || str_contains($sql, '`acars`'));

    expect($acarsQueries)->toBe([]);
});

test('the trail ends at the live position', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: ['lat' => 40.0, 'lon' => -80.0]);

    foreach ([[33.0, -90.0], [35.0, -88.0], [37.0, -85.0]] as $i => [$lat, $lon]) {
        Acars::factory()->create([
            'pirep_id'   => $pirep->id,
            'type'       => AcarsType::FLIGHT_PATH,
            'lat'        => $lat,
            'lon'        => $lon,
            'created_at' => Carbon::now('UTC')->subMinutes(10 - $i),
        ]);
    }

    $geo = app(GeoService::class)->getFeatureFromAcars($pirep->fresh());
    $coords = trailCoordinates($geo);

    // Three breadcrumbs plus the live position, in that order — the marker sits
    // at the end of its own track rather than floating ahead of it.
    expect($coords)->toHaveCount(4)
        ->and(end($coords))->toBe([-80.0, 40.0])
        ->and($geo['position'])->toBe(['lat' => 40.0, 'lon' => -80.0]);
});

test('the trail renders with no breadcrumbs yet', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: ['lat' => 30.0, 'lon' => -97.0]);

    $geo = app(GeoService::class)->getFeatureFromAcars($pirep->fresh());

    expect($geo['points']->getFeatures())->toHaveCount(1)
        ->and($geo['position'])->toBe(['lat' => 30.0, 'lon' => -97.0]);
});

test('appending the live position invents no intermediate points', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: ['lat' => 60.0, 'lon' => -10.0]);

    // Two breadcrumbs a long way apart, then a live position a long way from
    // both. Nothing may be interpolated to smooth either gap.
    foreach ([[10.0, -100.0], [30.0, -60.0]] as $i => [$lat, $lon]) {
        Acars::factory()->create([
            'pirep_id'   => $pirep->id,
            'type'       => AcarsType::FLIGHT_PATH,
            'lat'        => $lat,
            'lon'        => $lon,
            'created_at' => Carbon::now('UTC')->subMinutes(10 - $i),
        ]);
    }

    $coords = trailCoordinates(app(GeoService::class)->getFeatureFromAcars($pirep->fresh()));

    expect($coords)->toBe([[-100.0, 10.0], [-60.0, 30.0], [-10.0, 60.0]]);
});

test('the trail gains no duplicate point when the position matches the last breadcrumb', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: ['lat' => 37.0, 'lon' => -85.0]);

    Acars::factory()->create([
        'pirep_id'   => $pirep->id,
        'type'       => AcarsType::FLIGHT_PATH,
        'lat'        => 37.0,
        'lon'        => -85.0,
        'created_at' => Carbon::now('UTC'),
    ]);

    $geo = app(GeoService::class)->getFeatureFromAcars($pirep->fresh());

    // One breadcrumb, and the live position sits on top of it, so the trail is
    // a single point and gains no duplicate. (getLine() needs two points to
    // draw a line at all, hence the assertion on points rather than coords.)
    expect($geo['points']->getFeatures())->toHaveCount(1)
        ->and(trailCoordinates($geo))->toBe([]);
});

test('a single PIREP flown route still comes from acars', function (): void {
    $pirep = flightOnLiveMap(positionAttrs: ['lat' => 40.0, 'lon' => -80.0]);

    foreach ([[33.0, -90.0], [35.0, -88.0]] as $i => [$lat, $lon]) {
        Acars::factory()->create([
            'pirep_id'   => $pirep->id,
            'type'       => AcarsType::FLIGHT_PATH,
            'lat'        => $lat,
            'lon'        => $lon,
            'sim_time'   => Carbon::now('UTC')->subMinutes(10 - $i)->toIso8601String(),
            'created_at' => Carbon::now('UTC')->subMinutes(10 - $i),
        ]);
    }

    apiAs(User::find($pirep->user_id));
    $route = test()->get('/api/pireps/'.$pirep->id.'/acars/position')->assertOk()->json('data');

    // The recorded track is untouched by this change: same rows, same order,
    // and the live position is not among them.
    expect($route)->toHaveCount(2)
        ->and($route[0]['lat'])->toEqual(33.0)
        ->and($route[1]['lat'])->toEqual(35.0);
});
