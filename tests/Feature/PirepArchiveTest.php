<?php

use App\Enums\PirepState;
use App\Models\Aircraft;
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

    expect($data['flight'])->not->toBeNull()
        ->and($data['aircraft'])->not->toBeNull()
        ->and($data['simbrief'])->not->toBeNull()
        ->and($data['navlog'])->not->toBeNull();

    $simbrief = $data['simbrief'];
    expect($simbrief)->toHaveKeys(['general', 'aircraft', 'times'])
        ->and($simbrief['general'])->toHaveKeys([
            'cruise_profile', 'climb_profile', 'descent_profile', 'reserve_profile',
            'costindex', 'initial_altitude', 'stepclimb_string', 'route', 'route_distance', 'passengers',
        ])
        ->and($simbrief['aircraft'])->toHaveKeys(['icao_code', 'name', 'reg', 'internal_id', 'is_custom'])
        ->and($simbrief['times'])->toHaveKeys([
            'est_time_enroute', 'sched_time_enroute', 'sched_block', 'est_block', 'reserve_time',
        ]);

    // The full OFP has 41 keys per navlog fix; the archive keeps only 4.
    expect($data['navlog'])->not->toBeEmpty();
    foreach ($data['navlog'] as $fix) {
        expect(array_keys($fix))->toEqual(['ident', 'type', 'pos_lat', 'pos_long']);
    }
});

test('build() sums accepted flight time on the aircraft filed before this pirep', function (): void {
    $aircraft = Aircraft::factory()->create();

    $earlier = Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'aircraft_id'  => $aircraft->id,
        'submitted_at' => now()->subDays(2),
        'flight_time'  => 90,
    ]);
    $later = Pirep::factory()->create([
        'state'        => PirepState::ACCEPTED,
        'aircraft_id'  => $aircraft->id,
        'submitted_at' => now()->subDay(),
        'flight_time'  => 60,
    ]);
    $notAccepted = Pirep::factory()->create([
        'state'        => PirepState::REJECTED,
        'aircraft_id'  => $aircraft->id,
        'submitted_at' => now()->subDays(3),
        'flight_time'  => 500,
    ]);

    $pirep = Pirep::factory()->create([
        'state'        => PirepState::IN_PROGRESS,
        'aircraft_id'  => $aircraft->id,
        'submitted_at' => now(),
    ]);

    $data = app(PirepArchiveService::class)->build($pirep->fresh());

    expect($data['aircraft']['flight_time'])->toEqual(150);
});

test('file() archives flight, aircraft, and simbrief data', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    attachSimBriefOfp($pirep);

    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    $archive = PirepArchive::find($pirep->id);

    expect($archive)->not->toBeNull()
        ->and($archive->flight_id)->toEqual($pirep->flight_id)
        ->and($archive->flight)->not->toBeNull()
        ->and($archive->aircraft)->not->toBeNull()
        ->and($archive->simbrief)->not->toBeNull()
        ->and($archive->flight['callsign'])->toEqual($pirep->flight->callsign)
        ->and($archive->aircraft['registration'])->toEqual($pirep->aircraft->registration);
});

// No file() here on purpose: the frontend files a manual PIREP with
// create() + submit() and never touches file(), which is the ACARS API's path.
test('submit() on a manual pirep writes a sparse archive row', function (): void {
    $pirep = Pirep::factory()->create([
        'state'       => PirepState::IN_PROGRESS,
        'flight_id'   => null,
        'aircraft_id' => null,
    ]);

    app(PirepService::class)->submit($pirep);

    $archive = PirepArchive::find($pirep->id);

    expect($archive)->not->toBeNull()
        ->and($archive->flight_id)->toBeNull()
        ->and($archive->flight)->toBeNull()
        ->and($archive->simbrief)->toBeNull();
});

test('file() survives a simbrief row whose OFP file is missing', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);
    $simbrief = SimBrief::factory()->create([
        'user_id'       => $pirep->user_id,
        'flight_id'     => $pirep->flight_id,
        'aircraft_id'   => $pirep->aircraft_id,
        'pirep_id'      => $pirep->id,
        'ofp_json_path' => 'simbrief/does-not-exist.json',
    ]);

    expect($simbrief->images)->toBeEmpty()
        ->and($simbrief->files)->toBeEmpty();

    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    $archive = PirepArchive::find($pirep->id);

    expect($archive)->not->toBeNull()
        ->and($archive->flight)->not->toBeNull()
        ->and($archive->aircraft)->not->toBeNull()
        ->and($archive->simbrief)->toBeNull();
});

test('re-filing a pirep upserts the archive row', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    $pirep->update(['state' => PirepState::IN_PROGRESS]);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    expect(PirepArchive::where('pirep_id', $pirep->id)->count())->toEqual(1);
});

test('delete() removes the archive row', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    $pirepSvc = app(PirepService::class);
    $pirepSvc->file($pirep);
    $pirepSvc->submit($pirep);

    expect(PirepArchive::find($pirep->id))->not->toBeNull();

    $pirepSvc->delete($pirep->fresh());

    expect(PirepArchive::find($pirep->id))->toBeNull();
});
