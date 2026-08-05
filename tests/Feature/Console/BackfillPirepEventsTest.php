<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;

it('migrates a JSON blob row, mapping telemetry_id to acars_id', function (): void {
    $pirep = Pirep::factory()->create();
    $telemetry = Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::FLIGHT_PATH]);

    $blob = Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::LOG,
        'log'      => json_encode([
            'type'            => 'phase-change',
            'category'        => 'phase',
            'phase'           => 'takeoff',
            'log'             => 'Started takeoff',
            'payload'         => ['runway' => '27L'],
            'telemetry_id'    => $telemetry->id,
            'lat'             => 51.4775,
            'lon'             => -0.4614,
            'altitude_msl_ft' => 3500,
        ]),
    ]);

    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    $event = PirepEvent::findOrFail($blob->id);

    expect($event->pirep_id)->toBe($pirep->id)
        ->and($event->type)->toBe('phase-change')
        ->and($event->category)->toBe('phase')
        ->and($event->phase)->toBe('takeoff')
        ->and($event->log)->toBe('Started takeoff')
        ->and($event->details)->toBe(['runway' => '27L'])
        ->and($event->acars_id)->toBe($telemetry->id)
        ->and($event->lat)->toEqualWithDelta(51.4775, 0.001)
        ->and($event->lon)->toEqualWithDelta(-0.4614, 0.001)
        ->and($event->altitude_msl)->toEqualWithDelta(3500, 0.001);
});

it('nulls acars_id and reports the count when telemetry_id matches no acars row', function (): void {
    $pirep = Pirep::factory()->create();

    $blob = Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::LOG,
        'log'      => json_encode([
            'type'         => 'phase-change',
            'category'     => 'phase',
            'log'          => 'Started takeoff',
            'telemetry_id' => 'does-not-exist',
        ]),
    ]);

    $this->artisan('phpvms:backfill-pirep-events')
        ->expectsOutputToContain('1 referenced a missing telemetry row')
        ->assertExitCode(0);

    expect(PirepEvent::findOrFail($blob->id)->acars_id)->toBeNull();
});

it('runs a plain string row through the classifier', function (): void {
    $pirep = Pirep::factory()->create();

    $string = Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::LOG,
        'log'      => 'Flaps set to 15',
        'lat'      => 40.6413,
        'lon'      => -73.7781,
    ]);

    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    $event = PirepEvent::findOrFail($string->id);

    expect($event->type)->toBe('flaps-change')
        ->and($event->category)->toBe('aircraft')
        ->and($event->log)->toBe('Flaps set to 15')
        ->and($event->details)->toBe(['flaps' => '15'])
        ->and($event->lat)->toEqualWithDelta(40.6413, 0.001)
        ->and($event->lon)->toEqualWithDelta(-73.7781, 0.001)
        ->and($event->altitude_msl)->toBeNull();
});

it('leaves position null for a row that never reported one', function (): void {
    $pirep = Pirep::factory()->create();

    // acars.lat/lon are ->default(0), so a positionless log row reads back as
    // 0,0 — copying that verbatim would drop the event at Null Island.
    $string = Acars::factory()->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::LOG,
        'log'      => 'Flaps set to 15',
        'lat'      => 0,
        'lon'      => 0,
    ]);

    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    $event = PirepEvent::findOrFail($string->id);

    expect($event->lat)->toBeNull()
        ->and($event->lon)->toBeNull();
});

it('creates no duplicates when run a second time', function (): void {
    $pirep = Pirep::factory()->create();
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);

    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);
    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    expect(PirepEvent::where('pirep_id', $pirep->id)->count())->toBe(1);
});

it('leaves acars LOG rows in place without --delete', function (): void {
    $pirep = Pirep::factory()->create();
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);

    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    expect(Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::LOG)->count())->toBe(1);
});

it('purges LOG rows with --delete when confirmed, leaving other types untouched', function (): void {
    $pirep = Pirep::factory()->create();
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);
    $flightPath = Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::FLIGHT_PATH]);

    $this->artisan('phpvms:backfill-pirep-events', ['--delete' => true])
        ->expectsConfirmation(
            'This will permanently delete the migrated acars LOG rows. This cannot be undone. Continue?',
            'yes'
        )
        ->assertExitCode(0);

    expect(Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::LOG)->count())->toBe(0);
    expect(Acars::find($flightPath->id))->not->toBeNull();
});

it('skips the purge when declined interactively', function (): void {
    $pirep = Pirep::factory()->create();
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);

    $this->artisan('phpvms:backfill-pirep-events', ['--delete' => true])
        ->expectsConfirmation(
            'This will permanently delete the migrated acars LOG rows. This cannot be undone. Continue?',
            'no'
        )
        ->assertExitCode(0);

    expect(Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::LOG)->count())->toBe(1);
});

it('purges without prompting when run non-interactively', function (): void {
    $pirep = Pirep::factory()->create();
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);

    $this->artisan('phpvms:backfill-pirep-events', ['--delete' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::LOG)->count())->toBe(0);
});
