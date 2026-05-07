<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\ProcessAward;
use App\Models\Award;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Look for and run any of the award classes. Don't modify this.
 * See the documentation on creating awards:
 *
 * @url http://docs.phpvms.net/customizing/awards
 */
class AwardListener extends Listener // implements ShouldQueue
{
    // use Queueable;

    /**
     * Check for any awards to be run and test them against the user
     */
    public function handle(ProcessAward $event): void
    {
        /** @var Award[] $awards */
        $awards = Award::where('active', 1)->get();
        foreach ($awards as $award) {
            /** @var ?\App\Contracts\Award $klass */
            $klass = $award->getReference($award, $event->user);
            $klass?->handle();
        }
    }
}
