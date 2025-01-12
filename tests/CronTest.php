<?php

namespace Tests;

use App\Cron\Hourly\DeletePireps;
use App\Cron\Hourly\RemoveExpiredLiveFlights;
use App\Events\CronHourly;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;

final class CronTest extends TestCase
{
    /**
     * Create a new sample PIREP
     */
    protected static function getPirep($subtractTime): Pirep
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

    /**
     * @throws \Exception
     */
    public function test_expired_flight_not_being_removed(): void
    {
        $this->updateSetting('acars.live_time', 0);
        $pirep = $this->getPirep(2);

        /** @var RemoveExpiredLiveFlights $eventListener */
        $eventListener = app(RemoveExpiredLiveFlights::class);
        $eventListener->handle(new CronHourly());

        $found_pirep = Pirep::find($pirep->id);
        $this->assertNotNull($found_pirep);
    }

    /**
     * Delete flights that are more than X hours old and still in progress (no updates)
     *
     * @throws \Exception
     */
    public function test_expired_flight_should_not_be_removed(): void
    {
        $this->updateSetting('acars.live_time', 3);
        $pirep = $this->getPirep(2);

        /** @var RemoveExpiredLiveFlights $eventListener */
        $eventListener = app(RemoveExpiredLiveFlights::class);
        $eventListener->handle(new CronHourly());

        $found_pirep = Pirep::find($pirep->id);
        $this->assertNotNull($found_pirep);
    }

    /**
     * Delete flights that are more than X hours old and still in progress (no updates)
     *
     * @throws \Exception
     */
    public function test_expired_flight_should_be_removed(): void
    {
        $this->updateSetting('acars.live_time', 3);
        $pirep = $this->getPirep(4);

        /** @var RemoveExpiredLiveFlights $eventListener */
        $eventListener = app(RemoveExpiredLiveFlights::class);
        $eventListener->handle(new CronHourly());

        $found_pirep = Pirep::find($pirep->id);
        $this->assertNull($found_pirep);
    }

    /**
     * Delete flights that are more than X hours old and still in progress (no updates)
     *
     * @throws \Exception
     */
    public function test_completed_flights_should_not_be_deleted(): void
    {
        $this->updateSetting('acars.live_time', 3);
        $pirep = $this->getPirep(4);

        // Make sure the state is accepted
        $pirep->state = PirepState::ACCEPTED;
        $pirep->save();

        /** @var RemoveExpiredLiveFlights $eventListener */
        $eventListener = app(RemoveExpiredLiveFlights::class);
        $eventListener->handle(new CronHourly());

        $found_pirep = Pirep::find($pirep->id);
        $this->assertNotNull($found_pirep);
    }

    /**
     * Delete flights that are more than X hours old and have been rejected
     *
     * @throws \Exception
     */
    public function test_delete_rejected_pireps(): void
    {
        $this->updateSetting('pireps.delete_rejected_hours', 3);
        $pirep = $this->getPirep(4);

        // Make sure the state is accepted
        $pirep->state = PirepState::REJECTED;
        $pirep->save();

        /** @var DeletePireps $eventListener */
        $eventListener = app(DeletePireps::class);
        $eventListener->handle(new CronHourly());

        $found_pirep = Pirep::find($pirep->id);
        $this->assertNotNull($found_pirep);
    }

    /**
     * Delete flights that are more than X hours old and have been cancelled
     *
     * @throws \Exception
     */
    public function test_delete_cancelled_pireps(): void
    {
        $this->updateSetting('pireps.delete_cancelled_hours', 3);
        $pirep = $this->getPirep(4);

        // Make sure the state is accepted
        $pirep->state = PirepState::CANCELLED;
        $pirep->save();

        /** @var DeletePireps $eventListener */
        $eventListener = app(DeletePireps::class);
        $eventListener->handle(new CronHourly());

        $found_pirep = Pirep::find($pirep->id);
        $this->assertNotNull($found_pirep);
    }
}
