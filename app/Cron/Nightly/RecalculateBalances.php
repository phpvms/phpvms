<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Models\Journal;
use App\Repositories\JournalRepository;
use Illuminate\Support\Facades\Log;

/**
 * This recalculates the balances on all of the journals.
 */
class RecalculateBalances extends Listener
{
    private JournalRepository $journalRepo;

    /**
     * Nightly constructor.
     */
    public function __construct(JournalRepository $journalRepo)
    {
        $this->journalRepo = $journalRepo;
    }

    /**
     * Recalculate all the balances for the different ledgers.
     *
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Nightly: Recalculating balances');

        $journals = Journal::all();
        foreach ($journals as $journal) {
            $old_balance = $journal->balance;

            $this->journalRepo->recalculateBalance($journal);
            $journal->refresh();

            if (!$journal->balance->equals($old_balance)) {
                Log::info('Adjusting balance on '.
                    $journal->morphed_type.':'.$journal->morphed_id
                    .' from '.$old_balance.' to '.$journal->balance);
            }
        }

        Log::info('Done calculating balances');
    }
}
