<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Events\PirepFiled;
use App\Services\FlightService;
use App\Services\PirepService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class DiversionHandler extends Listener //implements ShouldQueue
{
    //use Queueable;

    public static $callbacks = [
        PirepFiled::class  => 'onPirepFiled',
        CronNightly::class => 'onCronNightly',
    ];

    public function __construct(
        private readonly FlightService $flightSvc,
        private readonly PirepService $pirepSvc
    ) {
    }

    /**
     * When a PIREP is filed, check for diversion
     *
     * @param PirepFiled $event
     */
    public function onPirepFiled(PirepFiled $event): void
    {
        $this->pirepSvc->handleDiversion($event->pirep);
    }

    /**
     * Every night, remove expired re-position flights
     *
     * @param CronNightly $event
     */
    public function onCronNightly(CronNightly $event): void
    {
        $this->flightSvc->removeExpiredRepositionFlights();
    }
}
