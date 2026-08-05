<?php

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\PirepArchive;
use App\Models\SimBrief;
use App\Services\PirepArchiveService;
use App\Services\PirepService;
use Illuminate\Support\Facades\Storage;

/**
 * Attach a SimBrief row backed by the sample OFP fixture to a pirep.
 */
function attachSimBriefOfp(Pirep $pirep): SimBrief
{
    $path = 'simbrief/'.$pirep->id.'.json';
    Storage::put($path, readDataFile('simbrief/briefing.json'));

    return SimBrief::factory()->create([
        'user_id'       => $pirep->user_id,
        'flight_id'     => $pirep->flight_id,
        'aircraft_id'   => $pirep->aircraft_id,
        'pirep_id'      => $pirep->id,
        'ofp_json_path' => $path,
    ]);
}

test('trims a full simbrief OFP down to the archive shape', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    attachSimBriefOfp($pirep);

    $data = app(PirepArchiveService::class)->build($pirep->fresh());

    expect($data)->toHaveKeys(['flight', 'aircraft', 'simbrief']);

    $simbrief = $data['simbrief'];
    expect($simbrief)->toHaveKeys(['general', 'aircraft', 'times', 'navlog'])
        ->and($simbrief['general'])->toHaveKeys([
            'cruise_profile', 'climb_profile', 'descent_profile', 'reserve_profile',
            'costindex', 'initial_altitude', 'stepclimb_string', 'route', 'route_distance', 'passengers',
        ])
        ->and($simbrief['aircraft'])->toHaveKeys(['icao_code', 'name', 'reg', 'internal_id', 'is_custom'])
        ->and($simbrief['times'])->toHaveKeys([
            'est_time_enroute', 'sched_time_enroute', 'sched_block', 'est_block', 'reserve_time',
        ]);

    // The full OFP has 41 keys per navlog fix; the archive keeps only 4.
    expect($simbrief['navlog'])->not->toBeEmpty();
    foreach ($simbrief['navlog'] as $fix) {
        expect(array_keys($fix))->toEqual(['ident', 'type', 'pos_lat', 'pos_long']);
    }
});

test('file() archives flight, aircraft, and simbrief data', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    attachSimBriefOfp($pirep);

    app(PirepService::class)->file($pirep);

    $archive = PirepArchive::find($pirep->id);

    expect($archive)->not->toBeNull()
        ->and($archive->flight_id)->toEqual($pirep->flight_id)
        ->and($archive->data)->toHaveKeys(['flight', 'aircraft', 'simbrief'])
        ->and($archive->data['flight']['callsign'])->toEqual($pirep->flight->callsign)
        ->and($archive->data['aircraft']['registration'])->toEqual($pirep->aircraft->registration);
});

test('file() on a manual pirep writes a sparse archive row', function (): void {
    $pirep = Pirep::factory()->create([
        'state'       => PirepState::IN_PROGRESS,
        'flight_id'   => null,
        'aircraft_id' => null,
    ]);

    app(PirepService::class)->file($pirep);

    $archive = PirepArchive::find($pirep->id);

    expect($archive)->not->toBeNull()
        ->and($archive->flight_id)->toBeNull()
        ->and($archive->data)->not->toHaveKey('flight')
        ->and($archive->data)->not->toHaveKey('simbrief');
});

test('file() survives a simbrief row whose OFP file is missing', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    SimBrief::factory()->create([
        'user_id'       => $pirep->user_id,
        'flight_id'     => $pirep->flight_id,
        'aircraft_id'   => $pirep->aircraft_id,
        'pirep_id'      => $pirep->id,
        'ofp_json_path' => 'simbrief/does-not-exist.json',
    ]);

    app(PirepService::class)->file($pirep);

    $archive = PirepArchive::find($pirep->id);

    expect($archive)->not->toBeNull()
        ->and($archive->data)->toHaveKeys(['flight', 'aircraft'])
        ->and($archive->data)->not->toHaveKey('simbrief');
});

test('re-filing a pirep upserts the archive row', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);

    $pirep->update(['state' => PirepState::IN_PROGRESS]);
    $pirepSvc->file($pirep);

    expect(PirepArchive::where('pirep_id', $pirep->id)->count())->toEqual(1);
});

test('delete() removes the archive row', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);

    expect(PirepArchive::find($pirep->id))->not->toBeNull();

    $pirepSvc->delete($pirep->fresh());

    expect(PirepArchive::find($pirep->id))->toBeNull();
});
