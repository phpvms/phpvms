<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;

/**
 * Wiring tests for `PirepLevelNormalizer` at the ACARS-facing write paths —
 * `prefile`, `update`, and `file` — the ones an ACARS client can reach
 * directly. `PirepLevelNormalizerTest.php` covers the coercion rule itself;
 * these prove it's actually called where the team lead asked ("PIREP update
 * and any file/submit path can also set level, not just PrefileRequest").
 *
 * `level` is deliberately NOT read-time normalized for map display —
 * openspec/changes/maplibre-map-platform/design.md open question 6 and D11
 * settled on ignoring `level` for the map entirely and deriving the
 * planned-route fallback from `altitude_msl` instead (see
 * MapPlannedRouteFallbackAltitudeTest.php). `level` stays ingest-normalized
 * here purely for the column's own sake.
 */
function levelNormalizationFixture(): array
{
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();
    $user = User::factory()->create(['rank_id' => $rank->id]);

    apiAs($user);

    return [
        'airport'  => Airport::factory()->create(),
        'airline'  => Airline::factory()->create(),
        'aircraft' => $subfleet->aircraft->random(),
    ];
}

test('prefile coerces a feet-valued level to a flight level', function (): void {
    $fixture = levelNormalizationFixture();

    $response = $this->post('/api/pireps/prefile', [
        'airline_id'     => $fixture['airline']->id,
        'aircraft_id'    => $fixture['aircraft']->id,
        'dpt_airport_id' => $fixture['airport']->icao,
        'arr_airport_id' => $fixture['airport']->icao,
        'flight_number'  => '6000',
        'level'          => 38000,
        'source_name'    => 'PirepLevelNormalizationTest::prefile',
    ]);

    $response->assertStatus(200);

    $pirep_id = $response->json('data.id');
    expect(Pirep::find($pirep_id)->level)->toEqual(380);
});

test('prefile leaves an already-correct flight level untouched', function (): void {
    $fixture = levelNormalizationFixture();

    $response = $this->post('/api/pireps/prefile', [
        'airline_id'     => $fixture['airline']->id,
        'aircraft_id'    => $fixture['aircraft']->id,
        'dpt_airport_id' => $fixture['airport']->icao,
        'arr_airport_id' => $fixture['airport']->icao,
        'flight_number'  => '6000',
        'level'          => 350,
        'source_name'    => 'PirepLevelNormalizationTest::prefile-fl',
    ]);

    $response->assertStatus(200);

    $pirep_id = $response->json('data.id');
    expect(Pirep::find($pirep_id)->level)->toEqual(350);
});

test('prefile leaves a null level untouched', function (): void {
    $fixture = levelNormalizationFixture();

    $response = $this->post('/api/pireps/prefile', [
        'airline_id'     => $fixture['airline']->id,
        'aircraft_id'    => $fixture['aircraft']->id,
        'dpt_airport_id' => $fixture['airport']->icao,
        'arr_airport_id' => $fixture['airport']->icao,
        'flight_number'  => '6000',
        'source_name'    => 'PirepLevelNormalizationTest::prefile-null',
    ]);

    $response->assertStatus(200);

    $pirep_id = $response->json('data.id');
    expect(Pirep::find($pirep_id)->level)->toBeNull();
});

test('the update endpoint coerces a feet-valued level to a flight level', function (): void {
    $fixture = levelNormalizationFixture();

    $prefile = $this->post('/api/pireps/prefile', [
        'airline_id'     => $fixture['airline']->id,
        'aircraft_id'    => $fixture['aircraft']->id,
        'dpt_airport_id' => $fixture['airport']->icao,
        'arr_airport_id' => $fixture['airport']->icao,
        'flight_number'  => '6000',
        'level'          => 350,
        'source_name'    => 'PirepLevelNormalizationTest::update',
    ])->assertStatus(200);

    $pirep_id = $prefile->json('data.id');

    $this->post('/api/pireps/'.$pirep_id.'/update', [
        'level' => 41000,
    ])->assertOk();

    expect(Pirep::find($pirep_id)->level)->toEqual(410);
});

test('the file endpoint coerces a feet-valued level to a flight level', function (): void {
    $fixture = levelNormalizationFixture();

    $prefile = $this->post('/api/pireps/prefile', [
        'airline_id'     => $fixture['airline']->id,
        'aircraft_id'    => $fixture['aircraft']->id,
        'dpt_airport_id' => $fixture['airport']->icao,
        'arr_airport_id' => $fixture['airport']->icao,
        'flight_number'  => '6000',
        'level'          => 350,
        'source_name'    => 'PirepLevelNormalizationTest::file',
    ])->assertStatus(200);

    $pirep_id = $prefile->json('data.id');

    $this->post('/api/pireps/'.$pirep_id.'/file', [
        'flight_time' => 130,
        'fuel_used'   => 8000.19,
        'distance'    => 400,
        'level'       => 39000,
    ])->assertStatus(200);

    expect(Pirep::find($pirep_id)->level)->toEqual(390);
});

test('coercion does not touch a previously stored feet-shaped value on an unrelated read', function (): void {
    // Simulates existing bad data written before this change (or by a write
    // path this change doesn't touch, e.g. the admin Filament form): ingest
    // normalization must not retroactively "fix" it. Bypasses every write
    // path above by writing the row directly.
    $pirep = Pirep::factory()->create(['level' => 35000]);

    expect(Pirep::find($pirep->id)->level)->toEqual(35000);
});
