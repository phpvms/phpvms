<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Models\Journal;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * This recalculates the balances on all of the journals
 */
class RecalculateBalances extends Listener
{
    /**
     * Recalculate all the balances for the different ledgers
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Nightly: Recalculating balances');

        // chunkById keeps memory bounded as the journal table grows.
        Journal::query()->chunkById(500, function ($journals): void {
            foreach ($journals as $journal) {
                $old_balance = $journal->balance;

                $journal->recalculateBalance();

                if (!$journal->balance->equals($old_balance)) {
                    Log::info('Adjusting balance on '
                        .$journal->morphed_type.':'.$journal->morphed_id
                        .' from '.$old_balance.' to '.$journal->balance);
                }
            }
        });

        Log::info('Done calculating balances');
    }
}
