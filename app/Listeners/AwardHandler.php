<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\ProcessAward;
use App\Models\Award;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Look for and run any of the award classes. Don't modify this.
 * See the documentation on creating awards:
 *
 * @url http://docs.phpvms.net/customizing/awards
 */
class AwardHandler extends Listener //implements ShouldQueue
{
    //use Queueable;

    /** The events and the callback */
    public static $callbacks = [
        ProcessAward::class => 'processAward',
    ];

    /**
     * @param ProcessAward $event
     *
     * @return void
     */
    public function processAward(ProcessAward $event): void
    {
        $this->checkForAwards($event->user);
    }

    /**
     * Check for any awards to be run and test them against the user
     *
     * @param \App\Models\User $user
     */
    public function checkForAwards($user): void
    {
        /** @var Award[] $awards */
        $awards = Award::where('active', 1)->get();
        foreach ($awards as $award) {
            /** @var \App\Contracts\Award $klass */
            $klass = $award->getReference($award, $user);
            $klass?->handle();
        }
    }
}
