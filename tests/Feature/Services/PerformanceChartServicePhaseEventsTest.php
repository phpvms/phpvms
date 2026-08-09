<?php

use App\Enums\AcarsType;
use App\Enums\PirepPhase;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;
use App\Services\Pirep\PerformanceChartService;
use Carbon\Carbon;

test('phases are derived from pirep_events phase rows, not log text scanning', function (): void {
    $pirep = Pirep::factory()->create();
    $start = Carbon::parse('2026-01-01 10:00:00');

    // FLIGHT_PATH samples with no per-sample status, so detectPhases falls
    // through to the LOG/event-derived tier.
    foreach (range(0, 4) as $i) {
        Acars::factory()->create([
            'pirep_id'   => $pirep->id,
            'type'       => AcarsType::FLIGHT_PATH,
            'status'     => null,
            'created_at' => $start->copy()->addMinutes($i * 10),
        ]);
    }

    // Phase-carrying pirep_events rows, in flight-time order. `log` deliberately
    // contains no marker text — phase detection must not need to scan it.
    PirepEvent::factory()->create([
        'pirep_id'   => $pirep->id,
        'phase'      => PirepPhase::TAXI->value,
        'log'        => 'opaque event text',
        'created_at' => $start->copy()->addMinutes(1),
    ]);
    PirepEvent::factory()->create([
        'pirep_id'   => $pirep->id,
        'phase'      => PirepPhase::TAKEOFF->value,
        'log'        => 'opaque event text',
        'created_at' => $start->copy()->addMinutes(11),
    ]);
    PirepEvent::factory()->create([
        'pirep_id'   => $pirep->id,
        'phase'      => PirepPhase::ENROUTE->value,
        'log'        => 'opaque event text',
        'created_at' => $start->copy()->addMinutes(21),
    ]);

    $result = app(PerformanceChartService::class)->buildDatasets($pirep);

    expect($result)->not->toBeNull();

    $codes = collect($result['phases'])->pluck('code')->all();

    expect($codes)->toBe([
        PirepPhase::TAXI->value,
        PirepPhase::TAKEOFF->value,
        PirepPhase::ENROUTE->value,
    ]);
});

test('enroute phase event is ignored before takeoff has been seen', function (): void {
    $pirep = Pirep::factory()->create();
    $start = Carbon::parse('2026-01-01 10:00:00');

    foreach (range(0, 2) as $i) {
        Acars::factory()->create([
            'pirep_id'   => $pirep->id,
            'type'       => AcarsType::FLIGHT_PATH,
            'status'     => null,
            'created_at' => $start->copy()->addMinutes($i * 10),
        ]);
    }

    // TAXI is matched (not after_takeoff), so the marker scan short-circuits
    // out of the VS-heuristic fallback. The ENROUTE marker fires (e.g. a
    // pre-takeoff "flaps set to up") with no TAKEOFF event ever seen — it
    // must be gated out, mirroring the old after_takeoff behaviour.
    PirepEvent::factory()->create([
        'pirep_id'   => $pirep->id,
        'phase'      => PirepPhase::TAXI->value,
        'created_at' => $start->copy()->addMinutes(1),
    ]);
    PirepEvent::factory()->create([
        'pirep_id'   => $pirep->id,
        'phase'      => PirepPhase::ENROUTE->value,
        'created_at' => $start->copy()->addMinutes(2),
    ]);

    $result = app(PerformanceChartService::class)->buildDatasets($pirep);

    expect($result)->not->toBeNull();

    $codes = collect($result['phases'])->pluck('code')->all();

    expect($codes)->toBe([PirepPhase::TAXI->value]);
});
