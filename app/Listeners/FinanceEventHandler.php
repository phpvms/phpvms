<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\PirepAccepted;
use App\Events\PirepRejected;
use App\Services\Finance\PirepFinanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Subscribe for events that we do some financial processing for
 * This includes when a PIREP is accepted, or rejected
 */
class FinanceEventHandler extends Listener implements ShouldQueue
{
    use Queueable;

    public static $callbacks = [
        PirepAccepted::class => 'onPirepAccept',
        PirepRejected::class => 'onPirepReject',
    ];

    public function __construct(
        private readonly PirepFinanceService $financeSvc
    ) {
    }

    /**
     * Kick off the finance events when a PIREP is accepted
     *
     * @param PirepAccepted $event
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function onPirepAccept(PirepAccepted $event): void
    {
        $this->financeSvc->processFinancesForPirep($event->pirep);
    }

    /**
     * Delete all finances in the journal for a given PIREP
     *
     * @param PirepRejected $event
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function onPirepReject(PirepRejected $event): void
    {
        $this->financeSvc->deleteFinancesForPirep($event->pirep);
    }
}
