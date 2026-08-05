<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\PirepArchive;
use Illuminate\Support\Facades\Artisan;

test('creates archives for accepted pireps lacking one', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);

    Artisan::call('pireps:archive-backfill');

    expect(PirepArchive::find($pirep->id))->not->toBeNull();
});

test('leaves existing archives untouched', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::ACCEPTED]);
    $archive = PirepArchive::factory()->create(['pirep_id' => $pirep->id, 'flight' => ['callsign' => 'ABC123']]);

    Artisan::call('pireps:archive-backfill');

    expect(PirepArchive::find($pirep->id)->flight)->toEqual($archive->flight);
});

test('skips pireps where no source resolves instead of writing an empty row', function (): void {
    $pirep = Pirep::factory()->create([
        'state'       => PirepState::ACCEPTED,
        'flight_id'   => null,
        'aircraft_id' => null,
    ]);

    Artisan::call('pireps:archive-backfill');

    expect(PirepArchive::find($pirep->id))->toBeNull();
});

test('ignores non-accepted states', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    Artisan::call('pireps:archive-backfill');

    expect(PirepArchive::find($pirep->id))->toBeNull();
});
