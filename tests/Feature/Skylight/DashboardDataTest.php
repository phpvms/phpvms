<?php

declare(strict_types=1);

use App\Enums\PirepFieldSource;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Http\Data\DashboardData;
use App\Models\Pirep;
use App\Models\PirepFieldValue;
use App\Models\Rank;
use App\Models\User;
use Carbon\Carbon;

function pilotDashboardData(User $user): DashboardData
{
    return DashboardData::fromUser($user->fresh());
}

function pilotDashboardPirep(User $user, array $attributes = []): Pirep
{
    return Pirep::factory()->create([
        'user_id'        => $user->id,
        'state'          => PirepState::ACCEPTED,
        'block_off_time' => Carbon::now('UTC')->subHour(),
        ...$attributes,
    ]);
}

test('aggregates accepted pilot score and landing rate only', function (): void {
    $rank = Rank::factory()->create(['hours' => 0]);
    $user = User::factory()->create([
        'rank_id'       => $rank->id,
        'flight_time'   => 90,
        'transfer_time' => 30,
        'state'         => UserState::ON_LEAVE,
    ]);

    pilotDashboardPirep($user, ['score' => 80, 'landing_rate' => -100]);
    pilotDashboardPirep($user, ['score' => 81, 'landing_rate' => -200]);
    pilotDashboardPirep($user, ['score' => null, 'landing_rate' => 0]);
    pilotDashboardPirep($user, ['state' => PirepState::PENDING, 'score' => 100, 'landing_rate' => -400]);
    pilotDashboardPirep(User::factory()->create(), ['score' => 100, 'landing_rate' => -400]);

    $dashboard = pilotDashboardData($user);

    expect($dashboard->id)->toBe($user->id)
        ->and($dashboard->name)->toBe($user->name)
        ->and($dashboard->state->label)->toBe(UserState::ON_LEAVE->getLabel())
        ->and($dashboard->state->color)->toBe(UserState::ON_LEAVE->getColor())
        ->and($dashboard->transferTimeMinutes)->toBe('0h 30m')
        ->and($dashboard->pilotScore)->toBe(81)
        ->and($dashboard->averageLandingRate)->toBe(-150);
});

test('returns null aggregate metrics when no measurable accepted PIREP values exist', function (): void {
    $user = User::factory()->create();

    pilotDashboardPirep($user, ['score' => null, 'landing_rate' => 0]);
    pilotDashboardPirep($user, ['state' => PirepState::PENDING, 'score' => 100, 'landing_rate' => -100]);

    $dashboard = pilotDashboardData($user);

    expect($dashboard->pilotScore)->toBeNull()
        ->and($dashboard->averageLandingRate)->toBeNull()
        ->and($dashboard->onTimePercentage)->toBeNull();
});

test('returns null metrics when the pilot has no PIREPs', function (): void {
    $dashboard = pilotDashboardData(User::factory()->create());

    expect($dashboard->pilotScore)->toBeNull()
        ->and($dashboard->averageLandingRate)->toBeNull()
        ->and($dashboard->onTimePercentage)->toBeNull();
});

test('preserves minimum and large stored career values', function (): void {
    $minimum = pilotDashboardData(User::factory()->create([
        'flights'       => 0,
        'flight_time'   => 0,
        'transfer_time' => 0,
    ]));
    $maximum = pilotDashboardData(User::factory()->create([
        'flights'       => 999999,
        'flight_time'   => 59999999,
        'transfer_time' => 5999999,
    ]));

    expect($minimum->flights)->toBe(0)
        ->and($minimum->flightTimeMinutes)->toBe('0h 0m')
        ->and($minimum->transferTimeMinutes)->toBe('0h 0m')
        ->and($maximum->flights)->toBe(999999)
        ->and($maximum->flightTimeMinutes)->toBe('999999h 59m')
        ->and($maximum->transferTimeMinutes)->toBe('99999h 59m');
});

test('uses the transfer-hours setting for absolute rank progress', function (): void {
    $current = Rank::factory()->create(['hours' => 0]);
    $next = Rank::factory()->create(['hours' => 3]);
    $user = User::factory()->create([
        'rank_id'       => $current->id,
        'flight_time'   => 90,
        'transfer_time' => 60,
    ]);

    updateSetting('pilots.count_transfer_hours', false);
    $withoutTransfer = pilotDashboardData($user)->rank;

    updateSetting('pilots.count_transfer_hours', true);
    $withTransfer = pilotDashboardData($user)->rank;

    expect($withoutTransfer)->not->toBeNull()
        ->and($withoutTransfer->from)->toBe($current->name)
        ->and($withoutTransfer->to)->toBe($next->name)
        ->and($withoutTransfer->currentHours)->toBe(1.5)
        ->and($withoutTransfer->targetHours)->toBe(3.0)
        ->and($withoutTransfer->hoursRemaining)->toBe(1.5)
        ->and($withoutTransfer->pct)->toBe(50)
        ->and($withTransfer)->not->toBeNull()
        ->and($withTransfer->currentHours)->toBe(2.5)
        ->and($withTransfer->hoursRemaining)->toBe(0.5)
        ->and($withTransfer->pct)->toBe(83);
});

test('handles missing, highest, and exceeded rank targets', function (): void {
    $withoutRank = User::factory()->create(['rank_id' => null]);
    expect(pilotDashboardData($withoutRank)->rank)->toBeNull();

    $highest = Rank::factory()->create(['hours' => 100000]);
    $highestUser = User::factory()->create(['rank_id' => $highest->id, 'flight_time' => 600]);
    $highestRank = pilotDashboardData($highestUser)->rank;

    expect($highestRank)->not->toBeNull()
        ->and($highestRank->to)->toBeNull()
        ->and($highestRank->currentHours)->toBe(10.0)
        ->and($highestRank->targetHours)->toBeNull()
        ->and($highestRank->hoursRemaining)->toBeNull()
        ->and($highestRank->pct)->toBe(100);

    $current = Rank::factory()->create(['hours' => 0]);
    $next = Rank::factory()->create(['hours' => 5]);
    $beyondTarget = User::factory()->create(['rank_id' => $current->id, 'flight_time' => 600]);
    $rank = pilotDashboardData($beyondTarget)->rank;

    expect($rank)->not->toBeNull()
        ->and($rank->to)->toBe($next->name)
        ->and($rank->currentHours)->toBe(10.0)
        ->and($rank->targetHours)->toBe(5.0)
        ->and($rank->hoursRemaining)->toBe(0.0)
        ->and($rank->pct)->toBe(100);
});

test('calculates on-time performance from accepted schedule snapshots', function (): void {
    $user = User::factory()->create();
    $scheduled = Carbon::parse('2026-08-10 20:00:00', 'UTC');

    $onTime = pilotDashboardPirep($user, [
        'scheduled_arrival_at' => $scheduled,
        'block_on_time'        => $scheduled->copy()->addMinutes(14),
    ]);
    pilotDashboardPirep($user, [
        'scheduled_arrival_at' => $scheduled,
        'block_on_time'        => $scheduled->copy()->addMinutes(15),
    ]);
    pilotDashboardPirep($user, [
        'scheduled_arrival_at' => $scheduled,
        'block_on_time'        => $scheduled->copy()->addMinutes(16),
    ]);
    pilotDashboardPirep($user, [
        'scheduled_arrival_at' => Carbon::parse('2026-08-11 05:00:00', 'UTC'),
        'block_on_time'        => Carbon::parse('2026-08-11 05:10:00', 'UTC'),
    ]);
    pilotDashboardPirep($user, [
        'scheduled_arrival_at' => Carbon::parse('2026-08-11 00:30:00', 'UTC'),
        'block_on_time'        => Carbon::parse('2026-08-11 00:44:00', 'UTC'),
    ]);
    pilotDashboardPirep($user, [
        'status'               => PirepPhase::DIVERTED,
        'scheduled_arrival_at' => $scheduled,
        'block_on_time'        => $scheduled->copy()->addMinutes(1),
    ]);
    $customFieldDiversion = pilotDashboardPirep($user, [
        'scheduled_arrival_at' => $scheduled,
        'block_on_time'        => $scheduled->copy()->addMinutes(1),
    ]);
    PirepFieldValue::create([
        'pirep_id' => $customFieldDiversion->id,
        'name'     => 'Diversion Airport',
        'value'    => 'KDEN',
        'source'   => PirepFieldSource::ACARS,
    ]);
    pilotDashboardPirep($user, ['scheduled_arrival_at' => null]);
    pilotDashboardPirep($user, [
        'state'                => PirepState::PENDING,
        'scheduled_arrival_at' => $scheduled,
        'block_on_time'        => $scheduled,
    ]);

    $onTime->flight->update(['arr_time' => '23:59']);

    expect(pilotDashboardData($user)->onTimePercentage)->toBe(42.9);
});
