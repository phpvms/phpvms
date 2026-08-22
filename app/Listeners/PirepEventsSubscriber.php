<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PirepAccepted;
use App\Events\PirepFiled;
use App\Events\PirepRejected;
use App\Features\Tour\TourService;
use App\Services\BidService;
use App\Services\Finance\PirepFinanceService;
use App\Services\PirepService;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Subscribe for events that we do some financial processing for
 * This includes when a PIREP is accepted, or rejected
 */
final readonly class PirepEventsSubscriber // implements ShouldQueue
{
    // use Queueable;

    public function __construct(
        private BidService $bidSvc,
        private PirepFinanceService $pirepFinanceSvc,
        private PirepService $pirepSvc,
        private TourService $tourSvc,
    ) {}

    /**
     * Kick off the finance events when a PIREP is accepted
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handlePirepAccepted(PirepAccepted $event): void
    {
        $this->pirepFinanceSvc->processFinancesForPirep($event->pirep);
    }

    /**
     * Delete all finances in the journal for a given PIREP
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handlePirepRejected(PirepRejected $event): void
    {
        $this->pirepFinanceSvc->deleteFinancesForPirep($event->pirep);
    }

    /**
     * @throws Exception
     */
    public function handlePirepFiled(PirepFiled $event): void
    {
        $this->bidSvc->removeBidForPirep($event->pirep);

        $flightId = $event->pirep->flight_id;
        $this->pirepSvc->handleDiversion($event->pirep);

        // A diversion breaks the chain: the aircraft is no longer where the
        // remaining legs depart from, so the run ends. handleDiversion() signals
        // it by nulling flight_id — see the "exclude this pirep from tour
        // checks" comment there — which is also why advance() must come after
        // it and why the flight id is read before.
        //
        // Detected here rather than on App\Events\PirepDiverted because
        // PirepService imports the broadcast notification of that name and
        // dispatches it instead, so the event never fires.
        if ($flightId !== null && $event->pirep->flight_id === null) {
            $this->tourSvc->cancelForFlight($event->pirep->user_id, $flightId);

            return;
        }

        $this->tourSvc->advance($event->pirep);
    }
}
