<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;

/**
 * `MapController::plannedFallbackAltitudeFt()` — what the planned route
 * draws at when a fix has no per-fix altitude. Deliberately derived from
 * `altitude_msl` telemetry, not `pireps.level` (design.md open question 6 /
 * D11: the map ignores `level` entirely, since ~93% of live vmsACARS-sourced
 * rows hold feet with no way to tell from the column alone).
 */
function pirepWithFlownTrack(array $pirepAttrs = []): Pirep
{
    $pirep = Pirep::factory()->create(array_merge([
        'state'  => PirepState::IN_PROGRESS,
        'status' => PirepPhase::ENROUTE,
    ], $pirepAttrs));

    PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
    ]);

    return $pirep;
}

function acarsPoint(string $pirepId, int $i, float $altitudeMsl, ?string $phase): void
{
    Acars::factory()->create([
        'pirep_id'     => $pirepId,
        'type'         => AcarsType::FLIGHT_PATH,
        'lat'          => $i * 0.1,
        'lon'          => $i * 0.1,
        'altitude_msl' => $altitudeMsl,
        'phase'        => $phase,
        'sim_time'     => now('UTC')->addMinutes($i)->toIso8601String(),
    ]);
}

test('the fallback altitude is the median of the ENROUTE-tagged points', function (): void {
    $pirep = pirepWithFlownTrack();

    acarsPoint($pirep->id, 0, 10000, PirepPhase::INIT_CLIM->value); // climb, excluded
    acarsPoint($pirep->id, 1, 30000, PirepPhase::ENROUTE->value);
    acarsPoint($pirep->id, 2, 35000, PirepPhase::ENROUTE->value);
    acarsPoint($pirep->id, 3, 34000, PirepPhase::ENROUTE->value);
    acarsPoint($pirep->id, 4, 5000, PirepPhase::APPROACH->value); // descent, excluded

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    // Sorted enroute altitudes: 30000, 34000, 35000 -> median 34000.
    expect($body['planned']['fallbackAltitudeFt'])->toEqual(34000);
});

test('the fallback altitude is the track maximum when no point is tagged ENROUTE', function (): void {
    $pirep = pirepWithFlownTrack();

    acarsPoint($pirep->id, 0, 5000, null);
    acarsPoint($pirep->id, 1, 32000, null);
    acarsPoint($pirep->id, 2, 8000, null);

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['planned']['fallbackAltitudeFt'])->toEqual(32000);
});

test('the fallback altitude is null when there is no flown track at all', function (): void {
    $pirep = pirepWithFlownTrack();

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['planned']['fallbackAltitudeFt'])->toBeNull();
});

test('the fallback altitude ignores pireps.level entirely', function (): void {
    // A deliberately absurd level value -- if it were read at all (raw or
    // coerced), it could not produce 36000, so this is a genuine
    // discriminator, not just "the fallback happens to be right".
    $pirep = pirepWithFlownTrack(['level' => 999999]);

    acarsPoint($pirep->id, 0, 36000, PirepPhase::ENROUTE->value);

    $body = test()->getJson('/api/map/pirep/'.$pirep->id)->assertOk()->json();

    expect($body['planned']['fallbackAltitudeFt'])->toEqual(36000);
});
