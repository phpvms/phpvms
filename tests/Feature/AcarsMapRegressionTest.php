<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;

/**
 * The map-api spec ("Existing acars endpoints unchanged") requires that adding
 * the new `/api/map/*` endpoints leaves `/api/acars` and `/api/acars/geojson`
 * exactly as they were — the seven theme and any third-party consumer poll
 * these directly. This file asserts their payload shape, independent of the
 * new map endpoints, so a future change to shared code (Pirep::scopeOnLiveMap,
 * GeoService, PirepResource) that drifts either shape is caught here.
 */
function acarsRegressionFlight(array $pirepAttrs = [], array $positionAttrs = []): Pirep
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

test('/api/acars keeps its existing top-level and nested resource shape', function (): void {
    acarsRegressionFlight();

    $response = test()->getJson('/api/acars')->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id', 'ident', 'phase', 'status_text',
                'dpt_airport_id', 'arr_airport_id',
                'distance', 'block_fuel', 'fuel_used', 'planned_distance',
                'aircraft'    => ['id', 'registration', 'name'],
                'airline'     => ['id', 'icao', 'name'],
                'dpt_airport' => ['id', 'icao', 'name'],
                'arr_airport' => ['id', 'icao', 'name'],
                'position'    => ['lat', 'lon', 'heading', 'distance'],
                'user',
                'fields',
            ],
        ],
    ]);
});

test('/api/acars/geojson keeps its existing FeatureCollection shape', function (): void {
    $pirep = acarsRegressionFlight(positionAttrs: ['lat' => 12.5, 'lon' => -30.25, 'heading' => 90, 'altitude_msl' => 33000]);

    $response = test()->getJson('/api/acars/geojson')->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'type',
            'features' => [
                '*' => [
                    'type',
                    'geometry'   => ['type', 'coordinates'],
                    'properties' => ['pirep_id', 'alt', 'heading'],
                ],
            ],
        ],
    ]);

    $feature = collect($response->json('data.features'))->firstWhere('properties.pirep_id', $pirep->id);

    expect($feature['geometry']['coordinates'])->toEqual([-30.25, 12.5, 33000])
        ->and($feature['properties']['heading'])->toBe(90);
});

test('/api/pireps/{id}/acars/geojson keeps its existing single-flight shape', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::FLIGHT_PATH,
        'lat'      => 1.0,
        'lon'      => 2.0,
    ]);

    $response = test()->getJson('/api/pireps/'.$pirep->id.'/acars/geojson')->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'position' => ['lat', 'lon'],
            'line',
            'points',
            'airports' => ['a' => ['icao', 'lat', 'lon'], 'd' => ['icao', 'lat', 'lon']],
        ],
    ]);
});
