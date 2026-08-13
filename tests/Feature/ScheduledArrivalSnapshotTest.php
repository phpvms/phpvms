<?php

use App\Enums\PirepState;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepArchive;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\PirepService;
use Carbon\Carbon;

function scheduledFlight(string $departureTimezone, string $arrivalTimezone, string $departureTime, string $arrivalTime): Flight
{
    $departure = Airport::factory()->create(['timezone' => $departureTimezone]);
    $arrival = Airport::factory()->create(['timezone' => $arrivalTimezone]);

    return Flight::factory()->create([
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
        'departure_time' => $departureTime,
        'arrival_time'   => $arrivalTime,
    ]);
}

function scheduledPirep(Flight $flight, string $blockOff): Pirep
{
    return Pirep::factory()->create([
        'airline_id'     => $flight->airline_id,
        'flight_id'      => $flight->id,
        'flight_number'  => $flight->flight_number,
        'dpt_airport_id' => $flight->dpt_airport_id,
        'arr_airport_id' => $flight->arr_airport_id,
        'block_off_time' => Carbon::parse($blockOff),
        'state'          => PirepState::IN_PROGRESS,
    ]);
}

test('snapshots a scheduled arrival in UTC across airport timezones', function (): void {
    $flight = scheduledFlight('America/Chicago', 'America/New_York', '08:00:00', '12:00:00');
    $pirep = scheduledPirep($flight, '2026-08-10T15:00:00Z');

    $pirep = app(PirepService::class)->update($pirep->id, ['notes' => 'started']);

    expect($pirep->scheduled_arrival_at?->toIso8601ZuluString())->toBe('2026-08-10T16:00:00Z');
});

test('rolls a scheduled arrival into the next day when its local time precedes departure', function (): void {
    $flight = scheduledFlight('America/Los_Angeles', 'America/New_York', '23:00:00', '07:00:00');
    $pirep = scheduledPirep($flight, '2026-08-11T06:30:00Z');

    $pirep = app(PirepService::class)->update($pirep->id, ['notes' => 'started']);

    expect($pirep->scheduled_arrival_at?->toIso8601ZuluString())->toBe('2026-08-11T11:00:00Z');
});

test('scheduled prefile uses its supplied creation time when block off is absent', function (): void {
    $flight = scheduledFlight('America/Los_Angeles', 'America/New_York', '23:00:00', '07:00:00');
    $subfleet = Subfleet::factory()->hasAircraft(1)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();
    $user = User::factory()->create(['rank_id' => $rank->id]);
    $aircraft = $subfleet->aircraft->first();
    $aircraft->update(['airport_id' => $flight->dpt_airport_id]);

    apiAs($user);

    $response = test()->post('/api/pireps/prefile', [
        'airline_id'     => $flight->airline_id,
        'aircraft_id'    => $aircraft->id,
        'flight_id'      => $flight->id,
        'flight_number'  => $flight->flight_number,
        'dpt_airport_id' => $flight->dpt_airport_id,
        'arr_airport_id' => $flight->arr_airport_id,
        'source_name'    => 'ScheduledArrivalSnapshotTest',
        'created_at'     => '2026-08-11T06:30:00Z',
    ]);

    $response->assertOk();

    $pirep = Pirep::findOrFail($response->json('data.id'));

    expect($pirep->getRawOriginal('block_off_time'))->toBeNull()
        ->and($pirep->scheduled_arrival_at?->toIso8601ZuluString())->toBe('2026-08-11T11:00:00Z');
});

test('keeps the first scheduled arrival after the source flight changes', function (): void {
    $flight = scheduledFlight('America/Chicago', 'America/New_York', '08:00:00', '12:00:00');
    $pirep = scheduledPirep($flight, '2026-08-10T15:00:00Z');
    $pirepService = app(PirepService::class);

    $pirep = $pirepService->update($pirep->id, ['notes' => 'started']);
    $flight->update(['arrival_time' => '15:00:00']);
    $pirep = $pirepService->update($pirep->id, [
        'notes'                => 'changed flight',
        'scheduled_arrival_at' => '2026-08-10T19:00:00Z',
    ]);

    expect($pirep->scheduled_arrival_at?->toIso8601ZuluString())->toBe('2026-08-10T16:00:00Z');
});

test('preserves the scheduled arrival in the filed archive', function (): void {
    $flight = scheduledFlight('America/Chicago', 'America/New_York', '08:00:00', '12:00:00');
    $pirep = scheduledPirep($flight, '2026-08-10T15:00:00Z');
    $pirepService = app(PirepService::class);

    $pirep = $pirepService->update($pirep->id, ['notes' => 'started']);
    $pirepService->file($pirep);

    $archive = PirepArchive::findOrFail($pirep->id);

    expect($archive->scheduled_arrival_at?->toIso8601ZuluString())->toBe('2026-08-10T16:00:00Z');
});

test('leaves manual PIREPs without a scheduled arrival', function (): void {
    $pirep = Pirep::factory()->create(['flight_id' => null]);

    $pirep = app(PirepService::class)->update($pirep->id, ['notes' => 'manual']);

    expect($pirep->scheduled_arrival_at)->toBeNull();
});
