<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\PirepFiled;
use App\Services\BidService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Do stuff with bids - like if a PIREP is accepted, then remove the bid.
 */
class BidEventHandler extends Listener // implements ShouldQueue
{
    // use Queueable;

    public static $callbacks = [
        PirepFiled::class => 'onPirepFiled',
    ];

    public function __construct(
        private readonly BidService $bidSvc
    ) {
    }

    /**
     * When a PIREP is filed, remove any bids.
     *
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function onPirepFiled(PirepFiled $event): void
    {
        $this->bidSvc->removeBidForPirep($event->pirep);
    }
}
