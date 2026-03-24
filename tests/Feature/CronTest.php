<?php

use App\Cron\Hourly\DeletePireps;
use App\Cron\Hourly\RemoveExpiredLiveFlights;
use App\Events\CronHourly;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;

/**
 * Create a new sample PIREP
 */
function createInProgressPirep($subtractTime): Pirep
{
    /** @var User $user */
    $user = User::factory()->create();

    /** @var Pirep $pirep */
    return Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::now('UTC')->subHours($subtractTime),
    ]);
}

test('expired flight not being removed', function () {
    updateSetting('acars.live_time', 0);
    $pirep = createInProgressPirep(2);

    /** @var RemoveExpiredLiveFlights $eventListener */
    $eventListener = app(RemoveExpiredLiveFlights::class);
    $eventListener->handle(new CronHourly());

    $found_pirep = Pirep::find($pirep->id);
    expect($found_pirep)->not->toBeNull();
});

test('expired flight should not be removed', function () {
    updateSetting('acars.live_time', 3);
    $pirep = createInProgressPirep(2);

    /** @var RemoveExpiredLiveFlights $eventListener */
    $eventListener = app(RemoveExpiredLiveFlights::class);
    $eventListener->handle(new CronHourly());

    $found_pirep = Pirep::find($pirep->id);
    expect($found_pirep)->not->toBeNull();
});

test('expired flight should be removed', function () {
    updateSetting('acars.live_time', 3);
    $pirep = createInProgressPirep(4);

    /** @var RemoveExpiredLiveFlights $eventListener */
    $eventListener = app(RemoveExpiredLiveFlights::class);
    $eventListener->handle(new CronHourly());

    $found_pirep = Pirep::find($pirep->id);
    expect($found_pirep)->toBeNull();
});

test('completed flights should not be deleted', function () {
    updateSetting('acars.live_time', 3);
    $pirep = createInProgressPirep(4);

    // Make sure the state is accepted
    $pirep->state = PirepState::ACCEPTED;
    $pirep->save();

    /** @var RemoveExpiredLiveFlights $eventListener */
    $eventListener = app(RemoveExpiredLiveFlights::class);
    $eventListener->handle(new CronHourly());

    $found_pirep = Pirep::find($pirep->id);
    expect($found_pirep)->not->toBeNull();
});

test('delete rejected pireps', function () {
    updateSetting('pireps.delete_rejected_hours', 3);
    $pirep = createInProgressPirep(4);

    // Make sure the state is accepted
    $pirep->state = PirepState::REJECTED;
    $pirep->save();

    /** @var DeletePireps $eventListener */
    $eventListener = app(DeletePireps::class);
    $eventListener->handle(new CronHourly());

    $found_pirep = Pirep::find($pirep->id);
    expect($found_pirep)->not->toBeNull();
});

test('delete cancelled pireps', function () {
    updateSetting('pireps.delete_cancelled_hours', 3);
    $pirep = createInProgressPirep(4);

    // Make sure the state is accepted
    $pirep->state = PirepState::CANCELLED;
    $pirep->save();

    /** @var DeletePireps $eventListener */
    $eventListener = app(DeletePireps::class);
    $eventListener->handle(new CronHourly());

    $found_pirep = Pirep::find($pirep->id);
    expect($found_pirep)->not->toBeNull();
});
