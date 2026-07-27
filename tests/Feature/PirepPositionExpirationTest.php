<?php

declare(strict_types=1);

use App\Cron\FiveMinute\PirepPositionExpiration;
use App\Cron\Hourly\RemoveExpiredLiveFlights;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Events\CronFiveMinute;
use App\Events\CronHourly;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Services\PirepService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function runExpiration(): void
{
    app(PirepPositionExpiration::class)->handle(new CronFiveMinute());
}

/**
 * A flight on the map. `$moved` false means it has sat at the gate since prefile,
 * which the row records as updated_at still equalling created_at.
 */
function flightOnMap(int $reportedAgo, array $pirepAttrs = [], bool $moved = true): Pirep
{
    $pirep = Pirep::factory()->create(array_merge([
        'state'  => PirepState::IN_PROGRESS,
        'status' => PirepPhase::ENROUTE,
    ], $pirepAttrs));

    PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
    ]);

    $last = Carbon::now('UTC')->subMinutes($reportedAgo);

    DB::table('pirep_positions')->where('pirep_id', $pirep->id)->update([
        'created_at' => $moved ? $last->copy()->subDay() : $last,
        'updated_at' => $last,
    ]);

    return $pirep;
}

function onMap(Pirep $pirep): bool
{
    return PirepPosition::where('pirep_id', $pirep->id)->exists();
}

beforeEach(function (): void {
    updateSetting('pireps.tombstone_time', 12);
    updateSetting('livemap.live_time', 30);
    updateSetting('livemap.idle_time', 60);
});

test('a completed flight past its window leaves the map', function (): void {
    $old = flightOnMap(reportedAgo: 45, pirepAttrs: ['state' => PirepState::PENDING]);
    $fresh = flightOnMap(reportedAgo: 15, pirepAttrs: ['state' => PirepState::PENDING]);

    runExpiration();

    expect(onMap($old))->toBeFalse()
        ->and(onMap($fresh))->toBeTrue();
});

test('a paused flight past its window leaves the map but survives as a PIREP', function (): void {
    $pirep = flightOnMap(reportedAgo: 90, pirepAttrs: ['status' => PirepPhase::PAUSED]);

    runExpiration();

    // A paused PIREP is paused deliberately: eviction must not touch the record.
    expect(onMap($pirep))->toBeFalse()
        ->and(Pirep::find($pirep->id))->not->toBeNull()
        ->and(Pirep::find($pirep->id)->state)->toBe(PirepState::IN_PROGRESS);
});

test('a paused flight within its window stays on the map', function (): void {
    $pirep = flightOnMap(reportedAgo: 30, pirepAttrs: ['status' => PirepPhase::PAUSED]);

    runExpiration();

    expect(onMap($pirep))->toBeTrue();
});

test('a paused flight is not reaped on the tombstone clock', function (): void {
    // idle_time governs map membership only, never reaping.
    $pirep = flightOnMap(reportedAgo: 60 * 20, pirepAttrs: ['status' => PirepPhase::PAUSED]);

    runExpiration();
    app(RemoveExpiredLiveFlights::class)->handle(new CronHourly());

    expect(onMap($pirep))->toBeFalse()
        ->and(Pirep::find($pirep->id))->not->toBeNull();
});

test('a prefiled flight that never departs is evicted on the stationary timer', function (): void {
    $stale = flightOnMap(reportedAgo: 90, pirepAttrs: ['status' => PirepPhase::INITIATED], moved: false);
    $recent = flightOnMap(reportedAgo: 30, pirepAttrs: ['status' => PirepPhase::INITIATED], moved: false);

    runExpiration();

    // Same timer as a paused flight: both are present and not moving.
    expect(onMap($stale))->toBeFalse()
        ->and(onMap($recent))->toBeTrue();
});

test('phase and state disagreeing resolves on state', function (): void {
    // Filed, but the last reported phase is still an arrived aircraft.
    $pirep = flightOnMap(reportedAgo: 45, pirepAttrs: [
        'state'  => PirepState::PENDING,
        'status' => PirepPhase::ARRIVED,
    ]);

    runExpiration();

    expect(onMap($pirep))->toBeFalse();
});

test('a completed flight is clocked from its last position, not its filing time', function (): void {
    // Landed 12:00, filed 15:00. submitted_at would draw it for 3.5 more hours.
    Carbon::setTestNow(Carbon::parse('2026-07-27 15:05:00', 'UTC'));

    $pirep = Pirep::factory()->create([
        'state'        => PirepState::PENDING,
        'status'       => PirepPhase::ARRIVED,
        'submitted_at' => Carbon::parse('2026-07-27 15:00:00', 'UTC'),
    ]);

    PirepPosition::factory()->create(['pirep_id' => $pirep->id, 'user_id' => $pirep->user_id]);

    DB::table('pirep_positions')->where('pirep_id', $pirep->id)->update([
        'created_at' => Carbon::parse('2026-07-27 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-07-27 12:00:00', 'UTC'),
    ]);

    runExpiration();

    // Gone on the landing clock, not the filing one.
    expect(onMap($pirep))->toBeFalse();

    Carbon::setTestNow();
});

test('zero disables a timer rather than expiring everything', function (): void {
    updateSetting('livemap.live_time', 0);
    updateSetting('livemap.idle_time', 0);

    $completed = flightOnMap(reportedAgo: 60 * 24, pirepAttrs: ['state' => PirepState::PENDING]);
    $paused = flightOnMap(reportedAgo: 60 * 24, pirepAttrs: ['status' => PirepPhase::PAUSED]);

    runExpiration();

    expect(onMap($completed))->toBeTrue()
        ->and(onMap($paused))->toBeTrue();
});

test('cancelling takes a flight off the map before the request completes', function (): void {
    $pirep = flightOnMap(reportedAgo: 1);

    app(PirepService::class)->cancel($pirep);

    // No expiration run in between - the cancel path is synchronous.
    expect(onMap($pirep))->toBeFalse();
});
