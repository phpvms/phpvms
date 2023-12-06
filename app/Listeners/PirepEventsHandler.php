<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\PirepPrefiled;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Handler for PIREP events
 */
class PirepEventsHandler extends Listener implements ShouldQueue
{
    use Queueable;

    /** The events and the callback */
    public static $callbacks = [
        PirepPrefiled::class => 'onPirepPrefile',
    ];

    /**
     * Called when a PIREP is prefiled
     *
     * @param PirepPrefiled $event
     */
    public function onPirepPrefile(PirepPrefiled $event)
    {
    }
}
