<?php

declare(strict_types=1);

use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Events\PirepUpdated;
use App\Models\Acars;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Prefile a flight through the API, as an ACARS client does.
 */
function prefileFlight(): array
{
    $subfleet = Subfleet::factory()->hasAircraft(1)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();
    $user = User::factory()->create(['rank_id' => $rank->id]);

    $dpt = Airport::factory()->create();
    $arr = Airport::factory()->create();
    $airline = Airline::factory()->create();
    $aircraft = $subfleet->aircraft->first();
    $aircraft->update(['airport_id' => $dpt->id]);

    apiAs($user);

    $response = test()->post('/api/pireps/prefile', [
        'airline_id'     => $airline->id,
        'aircraft_id'    => $aircraft->id,
        'flight_number'  => '1234',
        'source_name'    => 'PirepPositionWriteTest',
        'dpt_airport_id' => $dpt->icao,
        'arr_airport_id' => $arr->icao,
    ]);

    $response->assertStatus(200);

    return [Pirep::find($response->json('data.id')), $user];
}

function positionRow(Pirep $pirep): ?PirepPosition
{
    return PirepPosition::find($pirep->id);
}

/** `created_at` is collection time, which separates arrival from collection order. */
function postPositions(Pirep $pirep, array $positions): void
{
    test()->post('/api/pireps/'.$pirep->id.'/acars/position', ['positions' => $positions])
        ->assertStatus(200);
}

function point(Carbon $collectedAt, float $lat, float $lon, array $extra = []): array
{
    return array_merge([
        'lat'        => $lat,
        'lon'        => $lon,
        'created_at' => $collectedAt->toIso8601String(),
        'sim_time'   => $collectedAt->toIso8601String(),
    ], $extra);
}

test('prefiling puts the flight on the map at its departure airport', function (): void {
    [$pirep] = prefileFlight();
    $airport = Airport::find($pirep->dpt_airport_id);

    $position = positionRow($pirep);

    expect($position)->not->toBeNull()
        ->and((float) $position->lat)->toBe((float) $airport->lat)
        ->and((float) $position->lon)->toBe((float) $airport->lon)
        ->and($position->phase)->toBe(PirepPhase::SCHEDULED->value)
        ->and($position->user_id)->toBe($pirep->user_id);
});

test('telemetry not yet reported is zero rather than null', function (): void {
    [$pirep] = prefileFlight();

    $position = positionRow($pirep);

    expect($position->gs)->toBe(0)
        ->and($position->ias)->toBe(0)
        ->and($position->vs)->toBe(0.0)
        ->and($position->heading)->toBe(0)
        ->and($position->flight_time)->toBe(0)
        ->and($position->altitude_agl)->toBe(0.0)
        ->and($position->altitude_msl)->toBe(0.0)
        ->and($position->distance->internal())->toBe(0.0);
});

test('the first position batch replaces the seeded coordinates', function (): void {
    [$pirep] = prefileFlight();
    $seeded = positionRow($pirep);

    postPositions($pirep, [point(Carbon::now('UTC'), 41.5, -87.25)]);

    $position = positionRow($pirep);

    expect((float) $position->lat)->toBe(41.5)
        ->and((float) $position->lon)->toBe(-87.25)
        ->and((float) $position->lat)->not->toBe((float) $seeded->lat);
});

test('the position row reflects the newest point in a batch, whatever order it arrives in', function (): void {
    [$pirep] = prefileFlight();
    $at = Carbon::now('UTC');

    postPositions($pirep, [
        point($at->copy()->subMinutes(2), 10.0, 10.0),
        point($at->copy(), 12.0, 12.0),
        point($at->copy()->subMinute(), 11.0, 11.0),
    ]);

    expect((float) positionRow($pirep)->lat)->toBe(12.0);
});

test('repeated batches for one PIREP leave exactly one row', function (): void {
    [$pirep] = prefileFlight();
    $at = Carbon::now('UTC');

    for ($i = 1; $i <= 50; $i++) {
        postPositions($pirep, [point($at->copy()->addSeconds($i), $i, $i)]);
    }

    expect(DB::table('pirep_positions')->where('pirep_id', $pirep->id)->count())->toBe(1)
        ->and((float) positionRow($pirep)->lat)->toBe(50.0)
        ->and(Acars::where('pirep_id', $pirep->id)->flightPath()->count())->toBe(50);
});

test('a batch for a filed PIREP still moves the marker', function (): void {
    [$pirep] = prefileFlight();
    $at = Carbon::now('UTC');

    postPositions($pirep, [point($at->copy(), 33.0, 33.0)]);

    // Filing moves it to PENDING while the client may still be sending.
    $pirep->state = PirepState::PENDING;
    $pirep->save();

    postPositions($pirep, [point($at->copy()->addMinute(), 34.0, 34.0)]);

    expect((float) positionRow($pirep)->lat)->toBe(34.0);
});

test('a batch for an accepted PIREP writes acars but no position', function (): void {
    [$pirep] = prefileFlight();
    positionRow($pirep)->delete();

    $pirep->state = PirepState::ACCEPTED;
    $pirep->save();

    postPositions($pirep, [point(Carbon::now('UTC'), 33.0, 33.0)]);

    // The breadcrumb is still written: that contract is unchanged.
    expect(Acars::where('pirep_id', $pirep->id)->flightPath()->count())->toBe(1)
        ->and(positionRow($pirep))->toBeNull();
});

test('a late batch cannot return an evicted flight to the map', function (): void {
    [$pirep] = prefileFlight();
    $at = Carbon::now('UTC');

    postPositions($pirep, [point($at->copy()->subHour(), 12.0, 12.0)]);

    // Already reviewed, and its row already evicted.
    $pirep->state = PirepState::REJECTED;
    $pirep->save();

    positionRow($pirep)->delete();

    postPositions($pirep, [point($at->copy(), 13.0, 13.0)]);

    expect(positionRow($pirep))->toBeNull();
});

test('the position row updated_at moves on batches but not on an admin edit', function (): void {
    [$pirep] = prefileFlight();

    postPositions($pirep, [point(Carbon::now('UTC'), 5.0, 5.0)]);
    $afterBatch = positionRow($pirep)->updated_at;

    // Time has to pass, or "unchanged" proves nothing.
    Carbon::setTestNow(Carbon::now('UTC')->addHour());

    $pirep->notes = 'reviewed by an administrator';
    $pirep->save();

    expect(positionRow($pirep)->updated_at->timestamp)->toBe($afterBatch->timestamp);

    postPositions($pirep, [point(Carbon::now('UTC'), 6.0, 6.0)]);

    expect(positionRow($pirep)->updated_at->timestamp)->toBeGreaterThan($afterBatch->timestamp);

    Carbon::setTestNow();
});

test('the update endpoint writes no position row and emits what it always did', function (): void {
    [$pirep] = prefileFlight();
    positionRow($pirep)->delete();

    Event::fake([PirepUpdated::class]);

    test()->post('/api/pireps/'.$pirep->id.'/update', [
        'flight_time' => 90,
        'distance'    => 20,
        'status'      => PirepPhase::AIRBORNE->value,
    ])->assertStatus(200);

    Event::assertDispatched(PirepUpdated::class);

    expect(positionRow($pirep))->toBeNull()
        ->and(Pirep::find($pirep->id)->flight_time)->toBe(90);
});

test('the file endpoint writes no position row', function (): void {
    [$pirep] = prefileFlight();
    positionRow($pirep)->delete();

    test()->post('/api/pireps/'.$pirep->id.'/file', [
        'flight_time' => 60,
        'fuel_used'   => 100,
        'distance'    => 100,
    ])->assertStatus(200);

    expect(positionRow($pirep))->toBeNull();
});
