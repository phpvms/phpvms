<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Services\KvpService;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Whether the scheduler has ticked recently, judged from the heartbeat that
 * CronService::updateLastRunTime() writes every minute.
 */
final readonly class CronHealth
{
    public function __construct(private KvpService $kvp) {}

    public function isFresh(int $seconds = 300): bool
    {
        $last = $this->kvp->get('cron_last_run');

        if (!is_string($last) || $last === '') {
            return false;
        }

        try {
            $at = Carbon::parse($last);
        } catch (Throwable) {
            return false;
        }

        return $at->diffInSeconds(now(), false) <= $seconds;
    }
}
