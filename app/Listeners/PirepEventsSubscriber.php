<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PirepAccepted;
use App\Events\PirepFiled;
use App\Events\PirepRejected;
use App\Services\BidService;
use App\Services\Finance\PirepFinanceService;
use App\Services\PirepService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        $this->pirepSvc->handleDiversion($event->pirep);
    }
}
