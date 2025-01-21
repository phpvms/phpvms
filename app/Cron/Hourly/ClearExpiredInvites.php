<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Events\CronHourly;
use App\Models\Invite;
use Illuminate\Support\Facades\Log;

/**
 * Clear any expired invites
 */
class ClearExpiredInvites extends Listener
{
    public function handle(CronHourly $event): void
    {
        Log::info('Hourly: Removing expired invites');
        $invites = Invite::all();

        foreach ($invites as $invite) {
            if ($invite->expires_at && $invite->expires_at->isPast()) {
                $invite->delete();
            }

            if ($invite->usage_limit && $invite->usage_count >= $invite->usage_limit) {
                $invite->delete();
            }
        }
    }
}
